-- Phase 5 of docs/portfolios.md (email). Run this in production BEFORE deploying that phase (here,
-- and then rs-machine, whose portfolio_email task reads these). The code before it doesn't read
-- them, so running it early does no harm.
--
-- 1. dashboard_user_data.last_notified: when each user's email was last sent (or found to have
--    nothing in it). It starts empty, which the task takes to mean "now," so that the first
--    message has only what happens after the first run, rather than every action ever recorded.
--    (It isn't set to NOW() here: the database's clock is UTC, and bills_status.date_created is
--    Eastern time, as PHP writes it.)
-- 2. Hourly email is gone: hourly portfolios become daily, and notify can only be daily or none.
-- 3. email_log: each message sent (or not), so we can see what went out.
-- 4. An index on bills_status.date_created, which the task's query is by. bills_status is large,
--    so this one takes a while; it doesn't lock the table (LOCK=NONE), so the site carries on.
--
-- BEFORE RUNNING: look for long-running queries on these tables, and wait for them to finish:
--
--     SELECT id, user, time, state, LEFT(info, 100) AS query
--     FROM information_schema.processlist WHERE command <> 'Sleep' ORDER BY time DESC;
--
-- If a statement fails with "Lock wait timeout exceeded," nothing has changed; wait a minute and run
-- it again. Every statement can be run again safely.

SET SESSION lock_wait_timeout = 5;
SET SESSION innodb_lock_wait_timeout = 5;

-- 1.
ALTER TABLE dashboard_user_data
    ADD COLUMN IF NOT EXISTS last_notified DATETIME NULL DEFAULT NULL AFTER unsub_hash;

-- 2.
UPDATE dashboard_portfolios SET notify = 'daily' WHERE notify = 'hourly';
ALTER TABLE dashboard_portfolios
    MODIFY notify ENUM('daily','none') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'none';

-- 3.
CREATE TABLE IF NOT EXISTS email_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(8) UNSIGNED NOT NULL,
    bills SMALLINT UNSIGNED NOT NULL,
    actions SMALLINT UNSIGNED NOT NULL,
    success ENUM('y','n') NOT NULL,
    error VARCHAR(255) DEFAULT NULL,
    date_created DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id, date_created),
    KEY date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.
ALTER TABLE bills_status ADD INDEX IF NOT EXISTS date_created (date_created), ALGORITHM=INPLACE, LOCK=NONE;
