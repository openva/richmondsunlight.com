-- Adds bills.places_checked, which records when each bill was last checked for the places it names
-- (see rs-machine's cron/update_places.php). Run this in production before deploying that change.
--
-- BEFORE RUNNING: look for long-running queries on `bills` (a backup, an export, the search indexer),
-- and wait for them to finish:
--
--     SELECT id, user, time, state, LEFT(info, 100) AS query
--     FROM information_schema.processlist WHERE command <> 'Sleep' ORDER BY time DESC;
--
-- Why: changing a table's definition has to wait for every running query on it to finish, and every
-- query that starts in the meantime waits behind the change. A long query can therefore back up the
-- whole site. MariaDB will wait for a table's lock for a day by default, so the first statement
-- below makes this give up after 5 seconds instead. If a statement fails with "Lock wait timeout
-- exceeded," nothing has changed; wait a minute and run it again.
--
-- Every statement can be run again safely, and the steps are independent, so they can be run one at
-- a time. (phpMyAdmin's Import stops at the first error, so a failure doesn't run the later steps.)

SET SESSION lock_wait_timeout = 5;
SET SESSION innodb_lock_wait_timeout = 5;

-- 1. The column. INSTANT changes only the table's definition, with no rows touched, so it's quick no
--    matter how big the table is. If MariaDB couldn't do it that way, this fails, rather than
--    rebuilding the table.
ALTER TABLE `bills`
    ADD COLUMN IF NOT EXISTS `places_checked` datetime DEFAULT NULL
        COMMENT 'When the bill was last checked for the places it names (see bills_places). NULL means it needs checking.',
    ALGORITHM=INSTANT;

-- 2. An index on it, for finding the bills that haven't been checked. This is only to speed up that
--    search, and nothing breaks without it, so it can wait for a quiet time. It's built without
--    locking the table against reads or writes.
ALTER TABLE `bills`
    ADD INDEX IF NOT EXISTS `places_checked` (`places_checked`),
    ALGORITHM=INPLACE, LOCK=NONE;

-- 3. Bills that already have places tagged have been checked, so don't send them to GPT again. (The
--    "date_modified = date_modified" keeps the bills' own modification dates from changing.) Only
--    bills that haven't been marked yet are updated, so running this again does nothing.
UPDATE `bills`
SET `places_checked` = NOW(), `date_modified` = `date_modified`
WHERE `places_checked` IS NULL
    AND `id` IN (SELECT DISTINCT `bill_id` FROM `bills_places`);

-- 4. What that did: how many bills are waiting to be checked, by year. update_places.php checks bills
--    from 2024 on (RsMachine\PlaceNames::FIRST_YEAR), newest first. Earlier years are left alone, so
--    that checking them is a matter of lowering that year.
SELECT `sessions`.`year`, COUNT(*) AS `waiting`
FROM `bills`
JOIN `sessions` ON `sessions`.`id` = `bills`.`session_id`
WHERE `bills`.`places_checked` IS NULL
GROUP BY `sessions`.`year`
ORDER BY `sessions`.`year`;
