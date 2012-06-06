CREATE TABLE "category" ("category_id" INTEGER PRIMARY KEY    NOT NULL , "name" VARCHAR(60), "link" VARCHAR(512)); 
CREATE TABLE "description" 	("description_id" INTEGER PRIMARY KEY    NOT NULL , "description" TEXT); 
CREATE TABLE "plugin" 		("plugin_id" 	  INTEGER PRIMARY KEY    NOT NULL , "name" VARCHAR(60)); 
CREATE TABLE "result" 		("result_id" 	  INTEGER PRIMARY KEY    NOT NULL , "scan_id" INTEGER, "plugin_id" INTEGER, "category_id" INTEGER, "filename" VARCHAR(128), "line_number" INTEGER, "is_source_code" CHAR(1)); 
CREATE TABLE "scan" 		("scan_id" 	  INTEGER PRIMARY KEY    NOT NULL , "target" varchar(128), "scan_dt" DATETIME, "options" TEXT);
