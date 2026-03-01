Show the most recent Apache/PHP error log entries from the Docker web container.

```bash
docker compose exec web tail -50 /var/log/apache2/error.log
```
