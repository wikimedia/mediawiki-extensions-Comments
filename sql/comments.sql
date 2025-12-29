-- MySQL/SQLite schema for the Comments extension

CREATE TABLE /*_*/Comments (
  CommentID int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  Comment_Page_ID int(11) NOT NULL default 0,
  Comment_actor bigint unsigned NOT NULL,
  Comment_Text text NOT NULL,
  Comment_Date datetime NOT NULL default '1970-01-01 00:00:01',
  Comment_Parent_ID int(11) NOT NULL default 0
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/comment_page_id_index ON /*_*/Comments (Comment_Page_ID);
CREATE INDEX /*i*/wiki_actor ON /*_*/Comments (Comment_actor);
