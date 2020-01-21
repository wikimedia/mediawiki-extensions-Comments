-- MySQL/SQLite schema for the Comments extension

CREATE TABLE /*_*/Comments_block (
  cb_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cb_actor bigint unsigned NOT NULL,
  cb_actor_blocked bigint unsigned NOT NULL,
  cb_date datetime default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/cb_actor ON /*_*/Comments_block (cb_actor);