--
-- Give every video a slug (files.slug), for its page at /video/{slug}/. This file is named so that
-- it's loaded after all of the others that add videos.
--
-- In production, slugs are random (see Video::new_slug()). Here, each is made from the video's ID,
-- so that they're the same every time the database is loaded and tests can name them: the ID is
-- written in the slug alphabet (bcdfghjkmnpqrstvwxz23456789), with a letter first, so that no slug
-- is all digits. For example, video 15141 is "b334". That's unique for every ID up to 373,976.
--

UPDATE `files`
SET `slug` = CONCAT(
    SUBSTRING('bcdfghjkmnpqrstvwxz', (`id` DIV 19683) % 19 + 1, 1),
    SUBSTRING('bcdfghjkmnpqrstvwxz23456789', (`id` DIV 729) % 27 + 1, 1),
    SUBSTRING('bcdfghjkmnpqrstvwxz23456789', (`id` DIV 27) % 27 + 1, 1),
    SUBSTRING('bcdfghjkmnpqrstvwxz23456789', `id` % 27 + 1, 1)
)
WHERE `slug` IS NULL;
