FROM node:26.0.0-bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends git php-cli php-mbstring php-xml php-mysql ca-certificates python3 make g++ && rm -rf /var/lib/apt/lists/*
WORKDIR /opt/providers
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts && npm cache clean --force
ENV PATH="/opt/providers/node_modules/.bin:${PATH}"
WORKDIR /workspace
USER node
