-- Table structure for table LOOKUPS (TPL)

CREATE TABLE LOOKUPS (
  GUID integer primary key,
  ID varchar(10),
  APP varchar(10),
  CNAME varchar(20),
  LABEL varchar(100),
  POSITION integer DEFAULT 0
);

CREATE UNIQUE INDEX I_LOOKUPS_CNAME ON LOOKUPS (CNAME ASC);

/*Table structure for table `TRANSLATOR` */

CREATE TABLE `TRANSLATOR` (
  ID  integer primary key,
  TRANSLATOR integer,
  LANG integer,
  PAGE integer,
  TEXT_ID integer DEFAULT 0,
  TSTEXT ntext,
  DT datetime
);

/*Table structure for table `TRANSLATOR_LABELS` */

CREATE TABLE TRANSLATOR_LABELS (
  ID integer primary key,
  LABEL varchar(80),
  CATEGORY integer DEFAULT 0,
  DT datetime
);

CREATE INDEX I_TRANSLATOR_LABELS_LABEL ON TRANSLATOR_LABELS (LABEL);

-- Table structure for table AUTH_REGISTER

CREATE TABLE AUTH_REGISTER (
  USER_ID integer,
  ROLE_ID integer,
  OBJ_ID integer DEFAULT 0,
  RIGHT_ID integer,
  RVAL varchar(20) DEFAULT 0
);

CREATE UNIQUE INDEX I_AUTH_REGISTER_ROLE ON AUTH_REGISTER (ROLE_ID,OBJ_ID,RIGHT_ID);
CREATE UNIQUE INDEX I_AUTH_REGISTER_USER ON AUTH_REGISTER (USER_ID,OBJ_ID,RIGHT_ID);


-- Table structure for table AUTH_RIGHTS

CREATE TABLE AUTH_RIGHTS (
  ID integer primary key,
  SNAME varchar(100),
  ANNOT varchar(100),
  RTYPE char(1) DEFAULT 'B',
  DT datetime
);

-- Table structure for table AUTH_ROLES 

CREATE TABLE AUTH_ROLES (
  ID integer primary key,
  SNAME varchar(100),
  ANNOT varchar(100),
  AUTHOR_ID integer,
  LASTMOD datetime,
  DT datetime
);

-- Table structure for table AUTH_USER_ROLE 

CREATE TABLE AUTH_USER_ROLE (
  USER_ID integer,
  ROLE_ID integer,
  OBJ_ID integer DEFAULT 0,
  R_PRIORITY integer DEFAULT 1
);

CREATE UNIQUE INDEX I_AUTH_USER_ROLE_USER_ID ON AUTH_USER_ROLE (USER_ID,ROLE_ID,OBJ_ID);
CREATE INDEX I_AUTH_USER_ROLE_ROLE_ID ON AUTH_USER_ROLE (ROLE_ID);


-- Table structure for table AUTH_USERS 

CREATE TABLE AUTH_USERS (
  ID integer primary key,
  USERNAME varchar(255),
  FULLNAME varchar(255),
  EMAIL varchar(255),
  PASSW varchar(255),
  DPASSW varchar(50),
  ANNOT varchar(255),
  LAST_LOGIN datetime,
  LOGINFAIL integer DEFAULT 0,
  ACTIVE integer DEFAULT 1,
  AUTHOR_ID integer,
  LASTMOD datetime,
  DT datetime,
  IP integer
);

CREATE UNIQUE INDEX I_AUTH_USERS_USERNAME ON AUTH_USERS (USERNAME);

-- Table structure for table LOGGER

CREATE TABLE LOGGER (
  ID integer primary key,
  LOGGER integer,
  ACTION integer,
  CATEGORY integer,
  USER_ID integer DEFAULT 0,
  ITEM_ID integer,
  IP integer,
  UA integer,
  DT datetime
);

CREATE INDEX I_LOGGER_USER_ID ON LOGGER (USER_ID);
CREATE INDEX I_LOGGER_DT ON LOGGER (DT);
CREATE INDEX I_LOGGER_ACTION ON LOGGER (ACTION);

-- Table structure for table LOGGER_LABELS

CREATE TABLE LOGGER_LABELS (
  ID integer primary key,
  LABEL varchar(80),
  CATEGORY integer DEFAULT 0,
  DT datetime
);

CREATE INDEX I_LOGGER_LABELS_LABEL ON LOGGER_LABELS (LABEL);

-- Table structure for table LOGGER_MESSAGES

CREATE TABLE LOGGER_MESSAGES (
  LOG_ID primary key,
  LOGGER integer,  
  MESSAGE ntext,
  DT datetime
);

-- Table structure for table TREE_LOOKUPS (APP) 
CREATE TABLE TREE_LOOKUPS (
  ID integer primary key,
  TREE_ID integer,
  LABEL VARCHAR(50),
  LEVEL integer,
  URL VARCHAR(50),
  ROUTE VARCHAR(50),
  RKEY varchar(50),
  NR integer,
  ACTIVE integer DEFAULT 1
);

CREATE INDEX TREE_LOOKUPS_NR ON TREE_LOOKUPS (TREE_ID,NR);


-- Table structure for table APP_PARAMS

CREATE TABLE APP_PARAMS (
  ID integer primary key,
  PARAM_NAME VARCHAR(100) DEFAULT NULL,
  PARAM_VALUE VARCHAR(255) DEFAULT NULL,
  TITLE VARCHAR(255) DEFAULT NULL,
  CREATED_AT datetime,
  UPDATED_AT datetime,
  AUTHOR_ID integer DEFAULT NULL
);

/*Table structure for table `FILESTORAGE` */

CREATE TABLE FILESTORAGE (
  ID integer primary key,
  FILEPATH VARCHAR(255),
  ORIGNAME VARCHAR(255),
  ANNOT VARCHAR(255),
  HASH VARCHAR(16),
  ENTITY_ID integer,
  FILE_ID VARCHAR(100),
  ENTITY_TYPE VARCHAR(100),
  MIMETYPE VARCHAR(255),
  SIZE INT(11),
  USER_ID integer,
  DT datetime
);

CREATE INDEX I_FILESTORAGE_ENTITY (ENTITY_TYPE,ENTITY_ID);
CREATE INDEX I_FILESTORAGE_HASH ON FILESTORAGE (HASH);

CREATE TABLE jobs (
  id integer primary key,
  name VARCHAR(50),
  annotation ntext,
  job_command VARCHAR(255),
  job_params VARCHAR(255),
  first_run_at datetime,
  period integer,
  last_run_at datetime,
  last_run_result ntext,
  last_run_duration numeric,
  active integer DEFAULT 1,
  created_at datetime,
  author_id integer
);