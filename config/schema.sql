-- PostgreSQL schema for Braskit.
-- /*_*/ indicates the database prefix.

CREATE TABLE /*_*/boards (
    name text PRIMARY KEY CHECK (name <> ''),
    longname text NOT NULL,
    minlevel integer NOT NULL,
    lastid integer NOT NULL
);

CREATE TABLE /*_*/posts (
    globalid serial PRIMARY KEY,
    id integer NOT NULL,
    parent integer NOT NULL,
    board text NOT NULL REFERENCES /*_*/boards(name) ON DELETE CASCADE ON UPDATE CASCADE,
    timestamp timestamp NOT NULL,
    lastbump timestamp NOT NULL,
    ip inet,
    name text NOT NULL,
    tripcode text NOT NULL,
    email text NOT NULL,
    subject text NOT NULL,
    comment text NOT NULL,
    password text NOT NULL,
    permasaged boolean NOT NULL DEFAULT false,
    locked boolean NOT NULL DEFAULT false,
    metadata json,
    CHECK (parent = 0 OR parent < id),
    UNIQUE (board, id)
);

CREATE INDEX ON /*_*/posts (id, board);
CREATE INDEX ON /*_*/posts (parent);
CREATE INDEX ON /*_*/posts (timestamp);
CREATE INDEX ON /*_*/posts (lastbump);

CREATE TABLE /*_*/files (
    id serial PRIMARY KEY,
    postid integer NOT NULL,
    board text NOT NULL,
    file text NOT NULL CHECK (file <> ''),
    md5 text NOT NULL,
    origname text NOT NULL,
    shortname text NOT NULL,
    filesize integer NOT NULL,
    prettysize text NOT NULL,
    width integer NOT NULL,
    height integer NOT NULL,
    thumb text NOT NULL,
    t_width integer NOT NULL,
    t_height integer NOT NULL,
    filedata json
);

CREATE INDEX ON /*_*/files (postid, board);
CREATE INDEX ON /*_*/files (md5);

CREATE TABLE /*_*/bans (
    id serial PRIMARY KEY,
    ip cidr UNIQUE NOT NULL,
    timestamp timestamp NOT NULL,
    expire timestamp,
    reason text NOT NULL
);

CREATE INDEX ON /*_*/bans (ip);
CREATE INDEX ON /*_*/bans (expire);

CREATE TABLE /*_*/config (
    pool text NOT NULL,
    args text[] NOT NULL,
    key text NOT NULL,
    value jsonb NOT NULL,
    -- Make sure the number of percent signs in the pool name is the same as the
    -- number of arguments. If anyone knows of a better way of counting
    -- substrings, let me know.
    CHECK (
        array_length(regexp_split_to_array(pool, '%'), 1) - 1 =
        COALESCE(array_length(args, 1), 0)
    ),
    UNIQUE (pool, args, key)
);

CREATE INDEX ON /*_*/config (pool, args, key);

CREATE TABLE /*_*/reports (
    id serial PRIMARY KEY,
    postid integer NOT NULL,
    board text NOT NULL,
    ip inet NOT NULL,
    timestamp timestamp NOT NULL,
    reason text NOT NULL,
    FOREIGN KEY (postid, board) REFERENCES /*_*/posts(id, board) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE /*_*/users (
    username text PRIMARY KEY CHECK (username ~ '^\w+$'),
    password text NOT NULL,
    lastlogin timestamp,
    level integer NOT NULL CHECK (level BETWEEN 0 AND 9999) DEFAULT 0,
    email text NOT NULL DEFAULT '',
    capcode text NOT NULL DEFAULT ''
);

CREATE TABLE /*_*/spam (
    id serial PRIMARY KEY,
    rules text NOT NULL,
    diff text NOT NULL,
    username text REFERENCES /*_*/users ON DELETE SET NULL ON UPDATE CASCADE
);


--
-- Post insertion magic
--

CREATE FUNCTION /*_*/insert_post_func() RETURNS trigger AS $$
DECLARE
    updated_row RECORD;
    parent_row RECORD;
BEGIN
    -- Prevent race conditions
    LOCK TABLE /*_*/boards IN SHARE ROW EXCLUSIVE MODE;

    -- Update the board table's post ID counter.
    UPDATE /*_*/boards
        SET lastid = lastid + 1
        WHERE name = NEW.board
        RETURNING lastid INTO STRICT updated_row;

    NEW.id := updated_row.lastid;

    IF NEW.lastbump IS NULL THEN
        IF NEW.parent <> 0 THEN
            -- Set the lastbump column to the parent post's on replies. This is
            -- necessary for sorting purposes. Do we need to lock the table?
            SELECT lastbump
                INTO STRICT parent_row
                FROM /*_*/posts
                WHERE board = NEW.board AND id = NEW.parent;

            NEW.lastbump := parent_row.lastbump;
        ELSE
            -- Set the lastbump to the timestamp value on new threads.
            NEW.lastbump := NEW.timestamp;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger the above function when we INSERT into the posts row.
CREATE TRIGGER /*_*/insert_post_trigger
    BEFORE INSERT ON /*_*/posts
    FOR EACH ROW EXECUTE PROCEDURE /*_*/insert_post_func();


--
-- Post deletion
--

CREATE FUNCTION /*_*/delete_post(b TEXT, i INTEGER, p TEXT)
    RETURNS SETOF /*_*/posts_view AS $$
DECLARE
    row RECORD;
BEGIN
    FOR row IN
        SELECT * FROM /*_*/posts_view
            WHERE board = b AND (id = i OR parent = i)
            ORDER BY id ASC -- parent posts first
    LOOP
        -- check that the password matches. when we're deleting a thread, we
        -- know that we have permission to delete a post when a row in the loop
        -- has an id different from the id the function was called with.
        IF
            p IS NOT NULL
            AND row.id = i
            AND (row.password = '' OR row.password <> p)
        THEN
            RAISE EXCEPTION
                'Incorrect deletion password for post % on board %', i, b
                USING ERRCODE = 'invalid_password';

            RETURN;
        END IF;

        DELETE FROM /*_*/posts WHERE globalid = row.globalid;
        DELETE FROM /*_*/files WHERE id = row.fileid;

        RETURN NEXT row;
    END LOOP;

    RETURN;
END;
$$ LANGUAGE plpgsql;


-- Delete old threads at any given offset, for any given board.
-- Returns the posts being deleted.
CREATE FUNCTION /*_*/trim_board(b TEXT, o INTEGER)
    RETURNS SETOF /*_*/posts_view AS $$
DECLARE
    row RECORD;
BEGIN
    FOR row IN
        WITH RECURSIVE cte AS (
            SELECT * FROM (
                SELECT * FROM /*_*/posts
                    WHERE board = b AND parent = 0
                    ORDER BY lastbump DESC
                    OFFSET o
                ) AS fnord
            UNION ALL
                SELECT p.* FROM /*_*/posts AS p
                    JOIN cte AS c ON (p.board = c.board AND p.parent = c.id)
            )
        SELECT * FROM cte
    LOOP
        RETURN QUERY SELECT * FROM /*_*/delete_post(row.board, row.id, NULL);
    END LOOP;

    RETURN;
END;
$$ LANGUAGE plpgsql;


--
-- Bans view
--

CREATE VIEW /*_*/bans_view AS
    SELECT id, ip,
            -- IP address without the CIDR
            host(ip) AS host,
            -- CIDR
            masklen(ip) AS cidr,
            -- this is an ipv6 address (boolean)
            family(ip) <> 4 AS ipv6,
            -- this is a range ban (boolean)
            CASE WHEN family(ip) <> 4 THEN
                masklen(ip) <> 128
            ELSE
                masklen(ip) <> 32
            END AS range,
            timestamp, expire, reason
        FROM /*_*/bans;
