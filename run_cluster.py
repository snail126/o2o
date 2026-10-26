# -*- coding: utf-8 -*-
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler
from xgboost import XGBRegressor
import warnings
warnings.filterwarnings('ignore')

import pymysql
from sqlalchemy import create_engine

# ---------- 天气相关库 ----------
import openmeteo_requests
import requests_cache
from retry_requests import retry
# --------------------------------

DB_USER = 'root'
DB_PASSWORD = '123456'
DB_HOST = 'localhost'
DB_PORT = 3306
DB_NAME = 'pharm_order_db'

print("正在连接数据库...")
engine = create_engine(f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset=utf8mb4')
conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD,
                       database=DB_NAME, charset='utf8mb4')
cursor = conn.cursor()
print("数据库连接成功。")

# ==================== 天气函数 ====================
def get_weather_for_dates(lat, lon, start_date, end_date):
    cache_session = requests_cache.CachedSession('.cache', expire_after=-1)
    retry_session = retry(cache_session, retries=5, backoff_factor=0.2)
    openmeteo = openmeteo_requests.Client(session=retry_session)
    url = "https://archive-api.open-meteo.com/v1/archive"
    params = {
        "latitude": lat,
        "longitude": lon,
        "start_date": start_date,
        "end_date": end_date,
        "daily": ["temperature_2m_max", "temperature_2m_min", 
                  "precipitation_sum", "wind_speed_10m_max"],
        "timezone": "Asia/Shanghai"
    }
    responses = openmeteo.weather_api(url, params=params)
    response = responses[0]
    daily = response.Daily()
    daily_data = {
        "date": pd.date_range(
            start=pd.to_datetime(daily.Time(), unit="s"),
            end=pd.to_datetime(daily.TimeEnd(), unit="s"),
            freq=pd.Timedelta(seconds=daily.Interval()),
            inclusive="left"
        ),
        "temp_max": daily.Variables(0).ValuesAsNumpy(),
        "temp_min": daily.Variables(1).ValuesAsNumpy(),
        "precipitation": daily.Variables(2).ValuesAsNumpy(),
        "wind_speed": daily.Variables(3).ValuesAsNumpy()
    }
    df = pd.DataFrame(data=daily_data)
    df['date'] = df['date'].dt.date
    return df

def get_weather_forecast(lat, lon, days=7):
    cache_session = requests_cache.CachedSession('.cache', expire_after=-1)
    retry_session = retry(cache_session, retries=5, backoff_factor=0.2)
    openmeteo = openmeteo_requests.Client(session=retry_session)
    url = "https://api.open-meteo.com/v1/forecast"
    params = {
        "latitude": lat,
        "longitude": lon,
        "daily": ["temperature_2m_max", "temperature_2m_min", 
                  "precipitation_sum", "wind_speed_10m_max"],
        "timezone": "Asia/Shanghai",
        "forecast_days": days
    }
    responses = openmeteo.weather_api(url, params=params)
    response = responses[0]
    daily = response.Daily()
    dates = pd.date_range(
        start=pd.to_datetime(daily.Time(), unit="s"),
        periods=days,
        freq=pd.Timedelta(seconds=daily.Interval())
    )
    forecast_df = pd.DataFrame({
        'date': dates.date,
        'temp_max': daily.Variables(0).ValuesAsNumpy(),
        'temp_min': daily.Variables(1).ValuesAsNumpy(),
        'precipitation': daily.Variables(2).ValuesAsNumpy(),
        'wind_speed': daily.Variables(3).ValuesAsNumpy()
    })
    return forecast_df
# ==================================================

# 1. 读取数据
print("正在从数据库读取订单数据...")
order_df = pd.read_sql("""
    SELECT oi.create_time AS date, oi.community, od.drug_name, 
           od.buy_num AS quantity, od.unit_price, 
           od.buy_num * od.unit_price AS total_price,
           oi.user_id, oi.order_id
    FROM order_info oi
    JOIN order_detail od ON oi.order_id = od.order_id
""", conn)
order_df['date'] = pd.to_datetime(order_df['date'])
print(f"读取了 {len(order_df)} 条订单明细记录。")

# 2. user_info 检查
cursor.execute("SELECT COUNT(*) FROM user_info")
cnt = cursor.fetchone()[0]
if cnt == 0:
    print("user_info 为空，正在生成...")
    user_info = order_df.groupby('user_id').agg(
        first_buy_time=('date', 'min'),
        total_order_cnt=('order_id', 'nunique'),
        total_consume=('total_price', 'sum')
    ).reset_index()
    user_info['first_buy_time'] = user_info['first_buy_time'].dt.strftime('%Y-%m-%d %H:%M:%S')
    user_info.to_sql('user_info', engine, if_exists='append', index=False)
else:
    print(f"user_info 已有 {cnt} 条数据，跳过。")

# 3. RFM 和聚类
print("正在计算 RFM 和聚类...")
ref_date = order_df['date'].max() + timedelta(days=1)
rfm = order_df.groupby('user_id').agg(
    R=('date', lambda x: (ref_date - x.max()).days),
    F=('order_id', 'nunique'),
    M=('total_price', 'sum')
).reset_index()
rfm['R_score'] = pd.qcut(rfm['R'], 5, labels=[5,4,3,2,1])
rfm['F_score'] = pd.qcut(rfm['F'].rank(method='first'), 5, labels=[1,2,3,4,5])
rfm['M_score'] = pd.qcut(rfm['M'].rank(method='first'), 5, labels=[1,2,3,4,5])
features = rfm[['R', 'F', 'M']]
scaler = StandardScaler()
features_scaled = scaler.fit_transform(features)
kmeans = KMeans(n_clusters=4, random_state=42, n_init=10)
rfm['cluster_label'] = kmeans.fit_predict(features_scaled)
cluster_order = rfm.groupby('cluster_label').agg({'F': 'mean', 'M': 'mean', 'R': 'mean'}).reset_index()
cluster_order_sorted = cluster_order.sort_values('F', ascending=False)
name_list = ['慢病依赖型', '保健养生型', '流失预警型', '急症偶发型']
name_map = {}
for idx, row in cluster_order_sorted.iterrows():
    label = row['cluster_label']
    name_map[label] = name_list[idx] if idx < len(name_list) else '其他'
rfm['cluster_name'] = rfm['cluster_label'].map(name_map).fillna('未分类')
print("聚类名称分布：", rfm['cluster_name'].value_counts().to_dict())

portrait = rfm[['user_id', 'R_score', 'F_score', 'M_score', 'cluster_label', 'cluster_name']]
portrait.rename(columns={'R_score': 'r_score', 'F_score': 'f_score', 'M_score': 'm_score'}, inplace=True)
portrait['update_time'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
cursor.execute("TRUNCATE TABLE user_portrait")
conn.commit()
portrait.to_sql('user_portrait', engine, if_exists='append', index=False)
print(f"用户画像写入完成，共 {len(portrait)} 条。")

# 4. 销量预测（全局模型 + 每个药品专属重要性）
print("正在准备销量预测数据...")
daily_sales = order_df.groupby(['date', 'drug_name']).agg(daily_sales=('quantity', 'sum')).reset_index()

# ---------- 获取天气数据 ----------
print("正在获取历史天气数据...")
cursor.execute("SELECT pharmacy_lat, pharmacy_lon FROM order_info LIMIT 1")
pharmacy = cursor.fetchone()
if pharmacy and pharmacy[0] is not None and pharmacy[1] is not None:
    lon, lat = float(pharmacy[0]), float(pharmacy[1])
else:
    lat, lon = 31.99, 118.73
print(f"使用经纬度: 纬度 {lat}, 经度 {lon}")

min_date = daily_sales['date'].min().strftime('%Y-%m-%d')
max_date = daily_sales['date'].max().strftime('%Y-%m-%d')
weather_hist = get_weather_for_dates(lat, lon, min_date, max_date)
weather_hist['date'] = pd.to_datetime(weather_hist['date'])
daily_sales = daily_sales.merge(weather_hist, on='date', how='left')
daily_sales[['temp_max', 'temp_min', 'precipitation', 'wind_speed']] = \
    daily_sales[['temp_max', 'temp_min', 'precipitation', 'wind_speed']].ffill().fillna(0)
print("天气数据合并完成。")

daily_sales['weekday'] = daily_sales['date'].dt.dayofweek
daily_sales['month'] = daily_sales['date'].dt.month
daily_sales['is_weekend'] = daily_sales['weekday'].isin([5,6]).astype(int)

# 构造滞后特征（全局）
drug_list = daily_sales['drug_name'].unique()
print(f"共有 {len(drug_list)} 种药品，开始构造特征...")
all_data = []
for i, drug in enumerate(drug_list):
    if i % 10 == 0:
        print(f"  处理第 {i+1}/{len(drug_list)} 种药品...")
    drug_df = daily_sales[daily_sales['drug_name'] == drug].copy()
    drug_df = drug_df.drop(columns=['drug_name']).set_index('date').asfreq('D', fill_value=0).reset_index()
    drug_df['drug_name'] = drug
    drug_df['weekday'] = drug_df['date'].dt.dayofweek
    drug_df['month'] = drug_df['date'].dt.month
    drug_df['is_weekend'] = drug_df['weekday'].isin([5,6]).astype(int)
    for col in ['temp_max', 'temp_min', 'precipitation', 'wind_speed']:
        drug_df[col] = drug_df[col].ffill().fillna(0)
    for lag in range(1, 8):
        drug_df[f'lag_{lag}'] = drug_df['daily_sales'].shift(lag)
    drug_df['rolling_mean_7'] = drug_df['daily_sales'].rolling(7).mean()
    drug_df.dropna(inplace=True)
    all_data.append(drug_df)

forecast_df = pd.concat(all_data, ignore_index=True)
print("特征构造完成，开始训练全局 XGBoost 模型...")

feature_cols = ['weekday', 'month', 'is_weekend', 
            'lag_1','lag_2','lag_3','lag_4','lag_5','lag_6','lag_7',
            'rolling_mean_7',
            'temp_max', 'temp_min', 'precipitation', 'wind_speed']

X = forecast_df[feature_cols]
y = forecast_df['daily_sales']
max_date = forecast_df['date'].max()
train_mask = forecast_df['date'] < (max_date - timedelta(days=7))
X_train, X_test = X[train_mask], X[~train_mask]
y_train, y_test = y[train_mask], y[~train_mask]

model_global = XGBRegressor(n_estimators=100, learning_rate=0.1, max_depth=5, random_state=42)
model_global.fit(X_train, y_train)

# 全局特征重要性
global_imp = dict(zip(feature_cols, model_global.feature_importances_))
print("\n========== 全局特征重要性 ==========")
for name, imp in sorted(global_imp.items(), key=lambda x: x[1], reverse=True):
    print(f"  {name}: {imp:.4f}")
print("====================================\n")

# 保存全局重要性
cursor.execute("""
CREATE TABLE IF NOT EXISTS feature_importance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_name VARCHAR(64) NOT NULL,
    importance DECIMAL(10, 6) NOT NULL,
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
""")
conn.commit()
cursor.execute("TRUNCATE TABLE feature_importance")
conn.commit()
global_df = pd.DataFrame(
    sorted(global_imp.items(), key=lambda x: x[1], reverse=True),
    columns=['feature_name', 'importance']
)
global_df.to_sql('feature_importance', engine, if_exists='append', index=False)
print("全局特征重要性写入完成。")

# ========== 为每种药品训练专属模型（修复了类型错误） ==========
print("\n正在为每种药品训练专属模型（用于差异化重要性）...")
cursor.execute("""
CREATE TABLE IF NOT EXISTS drug_feature_importance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_name VARCHAR(128) NOT NULL,
    feature_name VARCHAR(64) NOT NULL,
    importance DECIMAL(10, 6) NOT NULL,
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
""")
conn.commit()
cursor.execute("TRUNCATE TABLE drug_feature_importance")
conn.commit()

drug_list_str = [str(d) for d in drug_list]
success_count = 0
fail_count = 0

for idx, drug_name in enumerate(drug_list_str):
    try:
        # 获取该药品数据，并复制
        drug_df_raw = daily_sales[daily_sales['drug_name'] == drug_name].copy()
        if len(drug_df_raw) < 10:
            print(f"  药品 {drug_name} 数据量不足 ({len(drug_df_raw)} 条)，跳过")
            fail_count += 1
            continue
        
        # 关键修复：先移除 drug_name 列，避免 asfreq 将其填充为 0
        drug_df_temp = drug_df_raw.drop(columns=['drug_name'])
        drug_df_full = drug_df_temp.set_index('date').asfreq('D', fill_value=0).reset_index()
        # 重新添加 drug_name 列
        drug_df_full['drug_name'] = drug_name
        
        # 计算星期、月份等
        drug_df_full['weekday'] = drug_df_full['date'].dt.dayofweek
        drug_df_full['month'] = drug_df_full['date'].dt.month
        drug_df_full['is_weekend'] = drug_df_full['weekday'].isin([5,6]).astype(int)
        # 天气填充
        for col in ['temp_max', 'temp_min', 'precipitation', 'wind_speed']:
            drug_df_full[col] = drug_df_full[col].ffill().fillna(0)
        # 滞后特征
        for lag in range(1, 8):
            drug_df_full[f'lag_{lag}'] = drug_df_full['daily_sales'].shift(lag)
        drug_df_full['rolling_mean_7'] = drug_df_full['daily_sales'].rolling(7).mean()
        drug_df_full.dropna(inplace=True)
        
        if len(drug_df_full) < 30:
            print(f"  药品 {drug_name} 有效数据量不足 ({len(drug_df_full)} 条)，跳过")
            fail_count += 1
            continue
        
        # 准备训练数据
        X_drug = drug_df_full[feature_cols]
        y_drug = drug_df_full['daily_sales']
        max_drug_date = drug_df_full['date'].max()
        train_mask_drug = drug_df_full['date'] < (max_drug_date - timedelta(days=7))
        if train_mask_drug.sum() < 10:
            print(f"  药品 {drug_name} 训练样本不足 ({train_mask_drug.sum()} 条)，跳过")
            fail_count += 1
            continue
        
        model_drug = XGBRegressor(n_estimators=100, learning_rate=0.1, max_depth=5, random_state=42)
        model_drug.fit(X_drug[train_mask_drug], y_drug[train_mask_drug])
        
        # 计算重要性并插入数据库
        drug_imp = dict(zip(feature_cols, model_drug.feature_importances_))
        for f_name, imp in drug_imp.items():
            cursor.execute(
                "INSERT INTO drug_feature_importance (drug_name, feature_name, importance) VALUES (%s, %s, %s)",
                (str(drug_name), str(f_name), float(imp))
            )
        conn.commit()
        success_count += 1
        if (idx + 1) % 5 == 0:
            print(f"  已处理 {idx+1}/{len(drug_list_str)} 种药品，成功 {success_count} 种")
    except Exception as e:
        fail_count += 1
        print(f"  药品 {drug_name} 训练失败: {e}")
        conn.rollback()
        continue

print(f"药品专属重要性训练完成：成功 {success_count} 种，跳过/失败 {fail_count} 种。")
# ======================================================================

print("模型训练完成，正在预测未来7天...")

future_weather = get_weather_forecast(lat, lon, 7)
future_weather['date'] = pd.to_datetime(future_weather['date']).dt.date
last_date = max_date
future_dates = [last_date + timedelta(days=i) for i in range(1, 8)]
predict_results = []

for drug in drug_list_str:
    drug_hist = daily_sales[daily_sales['drug_name'] == drug].tail(7)['daily_sales'].values.tolist()
    if len(drug_hist) < 7:
        drug_hist = [0]*(7-len(drug_hist)) + drug_hist
    last_7 = drug_hist.copy()
    for date in future_dates:
        w_row = future_weather[future_weather['date'] == date.date()]
        if not w_row.empty:
            t_max, t_min, prec, wind = w_row['temp_max'].values[0], w_row['temp_min'].values[0], w_row['precipitation'].values[0], w_row['wind_speed'].values[0]
        else:
            t_max, t_min, prec, wind = 20, 10, 0, 5
        
        feat = {
            'weekday': date.weekday(),
            'month': date.month,
            'is_weekend': 1 if date.weekday() in (5,6) else 0,
            'lag_1': last_7[-1] if len(last_7)>=1 else 0,
            'lag_2': last_7[-2] if len(last_7)>=2 else 0,
            'lag_3': last_7[-3] if len(last_7)>=3 else 0,
            'lag_4': last_7[-4] if len(last_7)>=4 else 0,
            'lag_5': last_7[-5] if len(last_7)>=5 else 0,
            'lag_6': last_7[-6] if len(last_7)>=6 else 0,
            'lag_7': last_7[-7] if len(last_7)>=7 else 0,
            'rolling_mean_7': np.mean(last_7) if len(last_7)>=7 else 0,
            'temp_max': t_max,
            'temp_min': t_min,
            'precipitation': prec,
            'wind_speed': wind
        }
        X_future = pd.DataFrame([feat])
        pred = model_global.predict(X_future)[0]
        predict_results.append({
            'drug_name': drug,
            'predict_date': date.strftime('%Y-%m-%d'),
            'predict_sales': round(max(pred, 0), 2)
        })
        last_7.append(pred)
        last_7.pop(0)

print("预测完成，正在写入数据库...")
cursor.execute("""
CREATE TABLE IF NOT EXISTS predict_result (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_name VARCHAR(128),
    predict_date DATE,
    predict_sales DECIMAL(10,2),
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
""")
conn.commit()
cursor.execute("TRUNCATE TABLE predict_result")
conn.commit()
predict_df = pd.DataFrame(predict_results)
predict_df['predict_date'] = pd.to_datetime(predict_df['predict_date']).dt.date
predict_df.to_sql('predict_result', engine, if_exists='append', index=False)
print(f"销量预测写入完成，共 {len(predict_df)} 条。")

cursor.close()
conn.close()
print("所有任务完成！")