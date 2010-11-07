-- $Id: mysql.sql,v 1.2 2001/12/04 23:49:33 carstenklapp Exp $

drop table if exists page;
CREATE TABLE page (
	id              INT NOT NULL,
        pagename        VARCHAR(100) BINARY NOT NULL,
	hits            INT NOT NULL DEFAULT 0,
        pagedata        MEDIUMTEXT NOT NULL DEFAULT '',
        PRIMARY KEY (id),
	UNIQUE KEY (pagename)
);

drop table if exists version;
CREATE TABLE version (
	id              INT NOT NULL,
        version         INT NOT NULL,
	mtime           INT NOT NULL,
	minor_edit      TINYINT DEFAULT 0,
        content         MEDIUMTEXT NOT NULL DEFAULT '',
        versiondata     MEDIUMTEXT NOT NULL DEFAULT '',
        PRIMARY KEY (id,version),
	INDEX (mtime)
);

drop table if exists recent;
CREATE TABLE recent (
	id              INT NOT NULL,
	latestversion   INT,
	latestmajor     INT,
	latestminor     INT,
        PRIMARY KEY (id)
);

drop table if exists nonempty;
CREATE TABLE nonempty (
	id              INT NOT NULL,
	PRIMARY KEY (id)
);

drop table if exists link;
CREATE TABLE link (
        linkfrom        INT NOT NULL,
        linkto          INT NOT NULL,
	INDEX (linkfrom),
        INDEX (linkto)
);

