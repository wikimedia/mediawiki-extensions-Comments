DROP SEQUENCE IF EXISTS Comments_CommentID_seq CASCADE;
CREATE SEQUENCE Comments_CommentID_seq;

-- Need to quote the name so that it doesn't get normalized to lowercase
CREATE TABLE "Comments" (
  CommentID INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('Comments_CommentID_seq'),
  Comment_Page_ID INTEGER NOT NULL DEFAULT 0,
  Comment_actor INTEGER NOT NULL DEFAULT 0,
  Comment_Text TEXT NOT NULL,
  Comment_Date TIMESTAMPTZ NOT NULL DEFAULT now(),
  Comment_Parent_ID INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS comment_page_id_index ON "Comments" (Comment_Page_ID);
CREATE INDEX IF NOT EXISTS wiki_actor ON "Comments" (Comment_actor);
