/*Table structure for table `LOOKUPS` (TPL) */

CREATE TABLE `LOOKUPS` (
  `GUID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ID` varchar(50) DEFAULT NULL,
  `APP` varchar(50) DEFAULT NULL,
  `CNAME` varchar(100) DEFAULT NULL,
  `LABEL` varchar(255) DEFAULT NULL,
  `POSITION` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`GUID`),
  KEY `CNAME` (`CNAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `TRANSLATOR` */

CREATE TABLE `TRANSLATOR` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TRANSLATOR` smallint(11) DEFAULT NULL,
  `LANG` smallint(6) DEFAULT '0',
  `PAGE` smallint(6) DEFAULT NULL,
  `TEXT_ID` int(11) DEFAULT '0',
  `TSTEXT` text,
  `DT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `TRANSLATOR_LABELS` */

CREATE TABLE `TRANSLATOR_LABELS` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `LABEL` varchar(100) DEFAULT NULL,
  `CATEGORY` tinyint(4) DEFAULT NULL,
  `DT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `LABEL` (`LABEL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/*Table structure for table `AUTH_REGISTER` */

CREATE TABLE `AUTH_REGISTER` (
  `USER_ID` int(11) DEFAULT NULL,
  `ROLE_ID` int(11) DEFAULT NULL,
  `OBJ_ID` int(11) DEFAULT '0',
  `RIGHT_ID` int(11) DEFAULT NULL,
  `RVAL` varchar(50) DEFAULT '0',
  UNIQUE KEY `I_ROLE` (`ROLE_ID`,`OBJ_ID`,`RIGHT_ID`),
  UNIQUE KEY `I_USER` (`USER_ID`,`OBJ_ID`,`RIGHT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/*Table structure for table `AUTH_RIGHTS` */

CREATE TABLE `AUTH_RIGHTS` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `SNAME` varchar(100) DEFAULT NULL,
  `ANNOT` varchar(255) DEFAULT NULL,
  `RTYPE` enum('B','C','I') DEFAULT 'B',
  `DT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `AUTH_ROLES` */

CREATE TABLE `AUTH_ROLES` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `SNAME` varchar(100) DEFAULT NULL,
  `ANNOT` varchar(255) DEFAULT NULL,
  `AUTHOR_ID` INT(11) DEFAULT NULL,
  `LASTMOD` datetime DEFAULT NULL,
  `DT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `AUTH_USER_ROLE` */

CREATE TABLE `AUTH_USER_ROLE` (
  `USER_ID` int(11) DEFAULT NULL,
  `ROLE_ID` int(11) DEFAULT NULL,
  `OBJ_ID` int(11) DEFAULT '0',
  `R_PRIORITY` int(11) DEFAULT '1',
  UNIQUE KEY `USER_ID` (`USER_ID`,`ROLE_ID`,`OBJ_ID`),
  KEY `ROLE_ID` (`ROLE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `AUTH_USERS` */

CREATE TABLE `AUTH_USERS` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `USERNAME` varchar(255) DEFAULT NULL,
  `FULLNAME` varchar(255) DEFAULT NULL,
  `EMAIL` varchar(255) DEFAULT NULL,
  `PASSW` varchar(255) DEFAULT NULL,
  `DPASSW` varchar(50) DEFAULT NULL,
  `ANNOT` varchar(255) DEFAULT NULL,
  `LAST_LOGIN` datetime DEFAULT NULL,
  `LOGINFAIL` tinyint(4) DEFAULT '0',
  `ACTIVE` tinyint(4) DEFAULT '1',
  `AUTHOR_ID` INT(11) DEFAULT NULL,
  `LASTMOD` datetime DEFAULT NULL,
  `DT` datetime DEFAULT NULL,
  `IP` bigint(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `USERNAME` (`USERNAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `LOGGER` */

CREATE TABLE `LOGGER` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `LOGGER` smallint(11) DEFAULT NULL,
  `ACTION` smallint(6) DEFAULT NULL,
  `CATEGORY` smallint(6),
  `USER_ID` int(11) DEFAULT '0',
  `ITEM_ID` int(11) DEFAULT NULL,
  `IP` bigint(11) DEFAULT NULL,
  `UA` smallint(6) DEFAULT NULL,
  `DT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `USER_ID` (`USER_ID`),
  KEY `DT` (`DT`),
  KEY `ACTION` (`ACTION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `LOGGER_LABELS` */

CREATE TABLE `LOGGER_LABELS` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `LABEL` varchar(100) DEFAULT NULL,
  `CATEGORY` tinyint(4) DEFAULT '0',
  `DT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `LABEL` (`LABEL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `LOGGER_MESSAGES` */

CREATE TABLE `LOGGER_MESSAGES` (
  `LOG_ID` int(11) NOT NULL,
  `LOGGER` smallint(11) DEFAULT NULL,  
  `MESSAGE` text,
  `DT` datetime DEFAULT NULL,
  PRIMARY KEY (`LOG_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `TREE_LOOKUPS` */

CREATE TABLE `TREE_LOOKUPS` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `TREE_ID` INT(11) DEFAULT NULL,
  `LABEL` VARCHAR(100) DEFAULT NULL,
  `LEVEL` TINYINT(4) DEFAULT NULL,
  `ROUTE` VARCHAR(100) DEFAULT NULL,
  `URL` VARCHAR(255) DEFAULT NULL,
  `RKEY` varchar(100) DEFAULT NULL,
  `NR` int(11) DEFAULT NULL,
  `ACTIVE` tinyint(4) DEFAULT '1',
   PRIMARY KEY (`ID`),
  KEY `I_NR` (`TREE_ID`,`NR`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `APP_PARAMS` */

CREATE TABLE `APP_PARAMS` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PARAM_NAME` VARCHAR(100) DEFAULT NULL,
  `PARAM_VALUE` VARCHAR(255) DEFAULT NULL,
  `TITLE` VARCHAR(255) DEFAULT NULL,
  `CREATED_AT` DATETIME DEFAULT NULL,
  `UPDATED_AT` DATETIME DEFAULT NULL,
  `AUTHOR_ID` INT(11) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `FILESTORAGE` */

CREATE TABLE `FILESTORAGE` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `FILEPATH` VARCHAR(255) DEFAULT NULL,
  `ORIGNAME` VARCHAR(255) DEFAULT NULL,
  `ANNOT` VARCHAR(255) DEFAULT NULL,
  `HASH` VARCHAR(16) DEFAULT NULL,
  `ENTITY_ID` INT(11) DEFAULT NULL,
  `FILE_ID` VARCHAR(100) DEFAULT NULL,
  `ENTITY_TYPE` VARCHAR(100) DEFAULT NULL,
  `MIMETYPE` VARCHAR(255) DEFAULT NULL,
  `SIZE` INT(11) DEFAULT NULL,
  `USER_ID` INT(11) DEFAULT NULL,
  `DT` DATETIME DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `i_entity` (`ENTITY_TYPE`,`ENTITY_ID`),
  UNIQUE KEY `i_hash` (`HASH`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `jobs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) DEFAULT NULL,
  `annotation` TEXT,
  `job_command` VARCHAR(255) DEFAULT NULL,
  `job_params` VARCHAR(255) DEFAULT NULL,
  `first_run_at` DATETIME DEFAULT NULL,
  `period` INT(11) DEFAULT NULL,
  `last_run_at` DATETIME DEFAULT NULL,
  `last_run_result` TEXT,
  `last_run_duration` decimal(10,2) DEFAULT NULL,
  `active` TINYINT(4) DEFAULT '1',
  `created_at` DATETIME DEFAULT NULL,
  `author_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/** Fill lookups. */

insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','0','job-period','Ruční spuštění','1');
insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','600','job-period','Jednou za 10 minut','3');
insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','3600','job-period','Jednou za hodinu','4');
insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','7200','job-period','Jednou za 2 hodiny','5');
insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','86400','job-period','Jednou za den','6');
insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','604800','job-period','Jednou za týden','7');
insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','2592000','job-period','Jednou za měsíc','8');
insert into `LOOKUPS` (`APP`, `ID`, `CNAME`, `LABEL`, `POSITION`) values('padmin','60','job-period','Jednou za minutu','2');

insert into `TRANSLATOR_LABELS` (`ID`, `LABEL`, `CATEGORY`) values(1,'App',1);

/** Version of PCLIB database structures. */  
INSERT INTO `APP_PARAMS` (`PARAM_NAME`, `PARAM_VALUE`, `TITLE`, `CREATED_AT`) VALUES('PCLIB_VERSION', '3.1.1', 'Version of PCLIB database structures', NOW());
