/* Version 2.6.0 -> Version 2.8.0 */

CREATE TABLE `APP_PARAMS`(
	`ID` int(11) NOT NULL  auto_increment , 
	`PARAM_NAME` varchar(100) COLLATE utf8_general_ci NULL  , 
	`PARAM_VALUE` varchar(255) COLLATE utf8_general_ci NULL  , 
	`TITLE` varchar(255) COLLATE utf8_general_ci NULL  , 
	`CREATED_AT` datetime NULL  , 
	`UPDATED_AT` datetime NULL  , 
	`AUTHOR_ID` int(11) NULL  , 
	PRIMARY KEY (`ID`) 
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Version 2.9.5 */

ALTER TABLE `FILESTORAGE` 
	ADD COLUMN `HASH` varchar(16)  COLLATE utf8_general_ci NULL after `ANNOT`, 
	CHANGE `ENTITY_ID` `ENTITY_ID` int(11)   NULL after `HASH`, 
	CHANGE `FILE_ID` `FILE_ID` varchar(100)  COLLATE utf8_general_ci NULL after `ENTITY_ID`, 
	CHANGE `ENTITY_TYPE` `ENTITY_TYPE` varchar(100)  COLLATE utf8_general_ci NULL after `FILE_ID`, 
	CHANGE `MIMETYPE` `MIMETYPE` varchar(255)  COLLATE utf8_general_ci NULL after `ENTITY_TYPE`, 
	CHANGE `SIZE` `SIZE` int(11)   NULL after `MIMETYPE`, 
	CHANGE `USER_ID` `USER_ID` int(11)   NULL after `SIZE`, 
	CHANGE `DT` `DT` datetime   NULL after `USER_ID`, 
	KEY `i_entity` (`ENTITY_TYPE`,`ENTITY_ID`),
	ADD UNIQUE KEY `i_hash`(`HASH`);

/* Version 3.0.0 */

ALTER TABLE `AUTH_ROLES` 
	ADD COLUMN `AUTHOR_ID` int(11)   NULL after `ANNOT`, 
	CHANGE `LASTMOD` `LASTMOD` datetime   NULL after `AUTHOR_ID`, 
	CHANGE `DT` `DT` datetime   NULL after `LASTMOD`;

ALTER TABLE `AUTH_USERS` 
	ADD COLUMN `AUTHOR_ID` int(11)   NULL after `ACTIVE`, 
	CHANGE `LASTMOD` `LASTMOD` datetime   NULL after `AUTHOR_ID`, 
	CHANGE `DT` `DT` datetime   NULL after `LASTMOD`, 
	CHANGE `IP` `IP` bigint(11)   NULL after `DT`;


/* Version 3.1.0 */

ALTER TABLE `AUTH_REGISTER` 
	CHANGE `RVAL` `RVAL` varchar(50)  COLLATE utf8_general_ci NULL DEFAULT '0' after `RIGHT_ID`;

ALTER TABLE `AUTH_RIGHTS` 
	CHANGE `ANNOT` `ANNOT` varchar(255)  COLLATE utf8_general_ci NULL after `SNAME`;

ALTER TABLE `AUTH_ROLES` 
	CHANGE `ANNOT` `ANNOT` varchar(255)  COLLATE utf8_general_ci NULL after `SNAME`;

ALTER TABLE `jobs` 
	CHANGE `name` `name` varchar(100)  COLLATE utf8_general_ci NULL after `id`, 
	CHANGE `job_command` `job_command` varchar(255)  COLLATE utf8_general_ci NULL after `annotation`, 
	ADD COLUMN `job_params` varchar(255)  COLLATE utf8_general_ci NULL after `job_command`, 
	CHANGE `first_run_at` `first_run_at` datetime   NULL after `job_params`, 
	DROP COLUMN `job_type`;

ALTER TABLE `LOGGER_LABELS` 
	CHANGE `LABEL` `LABEL` varchar(100)  COLLATE utf8_general_ci NULL after `ID`;

ALTER TABLE `LOOKUPS` 
	CHANGE `ID` `ID` varchar(50)  COLLATE utf8_general_ci NULL after `GUID`, 
	CHANGE `APP` `APP` varchar(50)  COLLATE utf8_general_ci NULL after `ID`, 
	CHANGE `CNAME` `CNAME` varchar(100)  COLLATE utf8_general_ci NULL after `APP`, 
	CHANGE `LABEL` `LABEL` varchar(255)  COLLATE utf8_general_ci NULL after `CNAME`;

ALTER TABLE `TRANSLATOR_LABELS` 
	CHANGE `LABEL` `LABEL` varchar(100)  COLLATE utf8_general_ci NULL after `ID`;

ALTER TABLE `TREE_LOOKUPS` 
	CHANGE `LABEL` `LABEL` varchar(100)  COLLATE utf8_general_ci NULL after `TREE_ID`, 
	CHANGE `ROUTE` `ROUTE` varchar(100)  COLLATE utf8_general_ci NULL after `LEVEL`, 
	CHANGE `URL` `URL` varchar(255)  COLLATE utf8_general_ci NULL after `ROUTE`, 
	CHANGE `RKEY` `RKEY` varchar(100)  COLLATE utf8_general_ci NULL after `URL`;


	/* Version 3.2.0 */

	CREATE TABLE `PCLIB_MAILS` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FROM` varchar(100) DEFAULT NULL,
  `TO` varchar(100) DEFAULT NULL,
  `RECIPIENTS` text,
  `SUBJECT` varchar(255) DEFAULT NULL,
  `BODY` text,
  `BODY_TEXT` text,
  `STATUS` tinyint(4) DEFAULT NULL,
  `ATTACHMENTS` text,
  `TEMPLATE_NAME` varchar(100) DEFAULT NULL,
  `SEND_AT` datetime DEFAULT NULL,
  `CREATED_AT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `PCLIB_CONTENT` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(50) DEFAULT NULL,
  `CATEGORY` varchar(50) DEFAULT NULL,
  `TITLE` varchar(100) DEFAULT NULL,
  `BODY` text,
  `CREATED_AT` datetime DEFAULT NULL,
  `UPDATED_AT` datetime DEFAULT NULL,
  `AUTHOR_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* Version 3.2.1 */

ALTER TABLE `AUTH_USERS` 
	ADD COLUMN `JSON_PARAMS` text  COLLATE utf8mb3_general_ci NULL after `ANNOT`;