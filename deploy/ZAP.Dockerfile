# Use the OWASP ZAP Docker image as the base
FROM owasp/zap2docker-stable:latest

# Set the working directory in the container
WORKDIR /zap

# Copy your application's specific ZAP scripts or configurations if any
# COPY zap-configs/ /zap/zap-configs/

# Entrypoint to keep the container running and to use ZAP CLI as needed
# This can be adjusted based on your specific use case
ENTRYPOINT ["zap.sh"]

# By default, run ZAP in daemon mode on a specific port if not overridden
CMD ["-daemon", "-host", "0.0.0.0", "-port", "8080", "-config", "api.disablekey=true"]
