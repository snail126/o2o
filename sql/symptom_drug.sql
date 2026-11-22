-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: localhost    Database: pharm_order_db
-- ------------------------------------------------------
-- Server version	8.0.41

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `symptom_drug`
--

DROP TABLE IF EXISTS `symptom_drug`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `symptom_drug` (
  `id` int NOT NULL AUTO_INCREMENT,
  `symptom` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `drug_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warning` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_symptom_drug` (`symptom`,`drug_name`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='病症-药品词典（病症推荐用）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `symptom_drug`
--

LOCK TABLES `symptom_drug` WRITE;
/*!40000 ALTER TABLE `symptom_drug` DISABLE KEYS */;
INSERT INTO `symptom_drug` VALUES (1,'感冒流感','感冒灵颗粒','感冒常规用药，销量领先','含对乙酰氨基酚，勿与其他感冒药或退烧药同服，服药期间忌酒'),(2,'感冒流感','感冒清热颗粒','风寒感冒常用中成药','风寒感冒适用，咽痛黄痰等风热感冒者不宜'),(3,'感冒流感','复方氨酚烷胺片','缓解感冒引起的发热头痛','含对乙酰氨基酚，勿与其他含同成分感冒药叠加服用'),(4,'感冒流感','复方氨酚烷胺胶囊','缓解感冒症状的复方制剂','含对乙酰氨基酚，勿与其他感冒药同服，肝肾功能不全者慎用'),(5,'感冒流感','小柴胡颗粒','感冒初期解表散热','与其他含西药成分感冒药同服需注意成分叠加'),(6,'感冒流感','抗病毒口服液','感冒抗病毒辅助用药','孕妇及风寒感冒者慎用'),(7,'感冒流感','磷酸奥司他韦胶囊','流感特效药，全店销量第一','处方药，建议发病48小时内遵医嘱使用'),(8,'感冒流感','磷酸奥司他韦颗粒','流感治疗用药（儿童适用剂型）','儿童需按体重遵医嘱给药'),(9,'感冒流感','玛巴洛沙韦片','新型抗流感病毒药物','处方药，不宜与牛奶及含钙饮品同服'),(10,'感冒流感','小儿豉翘清热颗粒','小儿感冒发热专用','儿童用药按年龄减量并遵医嘱'),(11,'发烧退热','布洛芬缓释胶囊','解热镇痛一线用药','胃肠道溃疡者慎用，急性高热建议选普通剂型'),(12,'发烧退热','复方对乙酰氨基酚片','快速退热镇痛','与感冒灵等含对乙酰氨基酚药品同服易过量伤肝'),(13,'发烧退热','医用退热贴','物理降温辅助退热','物理降温辅助，高热不退请及时就医'),(14,'发烧退热','小柴胡颗粒','低热寒热往来适用','与其他含西药成分感冒药同服需注意成分叠加'),(15,'咳嗽咳痰','宣肺止咳合剂','止咳化痰中成药','风寒咳嗽不宜，服药后不适请停用'),(16,'咳嗽咳痰','京都念慈菴蜜炼川贝枇杷膏','润肺止咳经典配方','含糖，糖尿病患者慎用'),(17,'咳嗽咳痰','强力批把露','化痰止咳常用药','含糖，糖尿病患者慎用'),(18,'咳嗽咳痰','鲜竹沥','清热化痰','脾胃虚寒、易腹泻者慎用'),(19,'咳嗽咳痰','小儿豉翘清热颗粒','小儿咳嗽伴发热适用','儿童用药按年龄减量并遵医嘱'),(20,'咽喉肿痛','金嗓子喉片','咽喉不适含片，携带方便','含服含化勿咀嚼吞服'),(21,'咽喉肿痛','桂林西瓜霜','咽喉肿痛经典用药','孕妇慎用'),(22,'咽喉肿痛','咽立爽口含滴丸','快速缓解咽痛','口含滴丸勿吞服，含化后余渣可吐出'),(23,'咽喉肿痛','西帕依固龈液','咽喉牙龈肿痛含漱','仅作含漱用，含漱后吐出勿吞服'),(24,'腹泻肠胃','蒙脱石散','止泻一线用药','与其他口服药间隔1-2小时服用，过量易便秘'),(25,'腹泻肠胃','肠炎宁片','肠道炎症调理','孕妇慎用'),(26,'腹泻肠胃','藿香正气水','肠胃不适兼解表化湿','含酒精，服用前后勿用头孢类抗生素，驾驶前不宜'),(27,'腹泻肠胃','丁桂儿脐贴','小儿腹泻外用辅助','皮肤破损处禁用'),(28,'中暑解暑','十滴水','中暑头晕常用','含酒精，孕妇禁用'),(29,'中暑解暑','藿香正气水','暑湿感冒、中暑呕吐','含酒精，服用前后勿用头孢类抗生素，驾驶前不宜'),(30,'中暑解暑','清凉油','提神醒脑缓解暑热不适','勿接触眼睛及破损皮肤，孕妇慎用'),(31,'中暑解暑','风油精','防暑提神、缓解头晕','孕妇及婴幼儿慎用'),(32,'感染炎症','头孢克肟胶囊','细菌感染常用抗生素（处方）','处方药，须遵医嘱使用，用药期间及停药一周内禁酒'),(33,'感染炎症','盐酸左氧氟沙星片','呼吸道尿路感染用药（处方）','18岁以下禁用，处方药须遵医嘱'),(34,'感染炎症','盐酸莫西沙星片','广谱抗感染（处方）','18岁以下禁用，处方药须遵医嘱'),(35,'感染炎症','阿奇霉素片','支原体感染常用药（处方）','须遵医嘱按疗程服用，勿与含铝镁成分胃药同服'),(36,'上火口腔','黄连上清片','清热泻火去火','脾胃虚寒、易腹泻者慎用'),(37,'上火口腔','桂林西瓜霜','口腔溃疡、咽喉肿痛','孕妇慎用'),(38,'上火口腔','金嗓子喉片','咽喉干痛含服','含服含化勿咀嚼吞服'),(39,'上火口腔','西帕依固龈液','牙龈肿痛出血','仅作含漱用，含漱后吐出勿吞服'),(40,'皮肤外伤','糠酸莫米松凝胶','皮肤炎症湿疹（处方）','处方药，不宜长期大面积使用'),(41,'皮肤外伤','接触性创面敷贴','创面保护防感染','深大伤口或出血不止请先就医'),(42,'皮肤外伤','医用棉签','伤口清洁护理耗材','一次性使用，注意保持干燥'),(43,'蚊虫叮咬','风油精','止痒消肿常备','孕妇及婴幼儿慎用'),(44,'蚊虫叮咬','清凉油','叮咬后清凉止痒','勿接触眼睛及破损皮肤，孕妇慎用');
/*!40000 ALTER TABLE `symptom_drug` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-22 11:42:11
