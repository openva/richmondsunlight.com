FROM linode/lamp
COPY run.sh /run.sh
RUN chmod +x /run.sh
CMD ["/bin/bash", "-lc", "/run.sh"]
ADD htdocs/ /var/www/example.com/public_html
