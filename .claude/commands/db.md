Run a MySQL query against the Docker database. Substitute the query as needed.

```bash
docker compose exec -T db mysql -u ricsun -ppassword richmondsunlight -e "$QUERY"
```

Example usage: run `SELECT id, name FROM people LIMIT 10;` against the database.
