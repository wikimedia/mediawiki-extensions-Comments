-- MySQL/SQLite schema for the Comments extension

CREATE TABLE /*_*/Comments_block (
  cb_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cb_user_id int(5) NOT NULL default 0,
  cb_user_name varchar(255) NOT NULL default '',
  cb_user_id_blocked int(5) default NULL,
  cb_user_name_blocked varchar(255) NOT NULL default '',
  cb_date datetime default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/cb_user_id ON /*_*/Comments_block (cb_user_id);
