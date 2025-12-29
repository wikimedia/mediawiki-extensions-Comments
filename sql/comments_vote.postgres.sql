-- Need to quote the name so that it doesn't get normalized to lowercase
CREATE TABLE "Comments_Vote" (
  Comment_Vote_ID INTEGER NOT NULL DEFAULT 0,
  Comment_Vote_actor INTEGER NOT NULL DEFAULT 0,
  Comment_Vote_Score INTEGER NOT NULL DEFAULT 0,
  Comment_Vote_Date TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS Comments_Vote_actor_index ON "Comments_Vote" (Comment_Vote_ID,Comment_Vote_actor);
CREATE INDEX IF NOT EXISTS Comment_Vote_Score ON "Comments_Vote" (Comment_Vote_Score);
CREATE INDEX IF NOT EXISTS Comment_Vote_actor ON "Comments_Vote" (Comment_Vote_actor);
