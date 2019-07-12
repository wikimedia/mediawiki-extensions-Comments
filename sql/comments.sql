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
