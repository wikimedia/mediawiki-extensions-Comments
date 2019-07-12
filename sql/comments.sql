-- MySQL/SQLite schema for the Comments extension
CREATE TABLE /*_*/Comments (
  CommentID int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  Comment_Page_ID int(11) NOT NULL default 0,
  Comment_user_id int(11) NOT NULL default 0,
  Comment_Username varchar(200) NOT NULL default '',
  Comment_Text text NOT NULL,
  Comment_Date datetime NOT NULL default '1970-01-01 00:00:01',
  Comment_Parent_ID int(11) NOT NULL default 0,
  Comment_IP varchar(45) NOT NULL default ''
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/comment_page_id_index ON /*_*/Comments (Comment_Page_ID);
CREATE INDEX /*i*/wiki_user_id ON /*_*/Comments (Comment_user_id);
CREATE INDEX /*i*/wiki_user_name ON /*_*/Comments (Comment_Username);

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

CREATE TABLE /*_*/Comments_block (
  cb_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cb_user_id int(5) NOT NULL default 0,
  cb_user_name varchar(255) NOT NULL default '',
  cb_user_id_blocked int(5) default NULL,
  cb_user_name_blocked varchar(255) NOT NULL default '',
  cb_date datetime default NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/cb_user_id ON /*_*/Comments_block (cb_user_id);
