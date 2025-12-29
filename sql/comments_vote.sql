-- MySQL/SQLite schema for the Comments extension

CREATE TABLE /*_*/Comments_Vote (
  Comment_Vote_ID int(11) NOT NULL default 0,
  Comment_Vote_actor bigint unsigned NOT NULL,
  Comment_Vote_Score int(4) NOT NULL default 0,
  Comment_Vote_Date datetime NOT NULL default '1970-01-01 00:00:01'
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/Comments_Vote_actor_index ON /*_*/Comments_Vote (Comment_Vote_ID,Comment_Vote_actor);
CREATE INDEX /*i*/Comment_Vote_Score ON /*_*/Comments_Vote (Comment_Vote_Score);
CREATE INDEX /*i*/Comment_Vote_actor ON /*_*/Comments_Vote (Comment_Vote_actor);
