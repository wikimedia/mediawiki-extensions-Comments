-- MySQL/SQLite schema for the Comments extension

CREATE TABLE /*_*/Comments_Vote (
  Comment_Vote_ID int(11) NOT NULL default 0,
  Comment_Vote_user_id int(11) NOT NULL default 0,
  Comment_Vote_Username varchar(200) NOT NULL default '',
  Comment_Vote_Score int(4) NOT NULL default 0,
  Comment_Vote_Date datetime NOT NULL default '1970-01-01 00:00:01',
  Comment_Vote_IP varchar(45) NOT NULL default ''
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/Comments_Vote_user_id_index ON /*_*/Comments_Vote (Comment_Vote_ID,Comment_Vote_Username);
CREATE INDEX /*i*/Comment_Vote_Score ON /*_*/Comments_Vote (Comment_Vote_Score);
CREATE INDEX /*i*/Comment_Vote_user_id ON /*_*/Comments_Vote (Comment_Vote_user_id);
