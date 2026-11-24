# BookHub client — multi-stage production image.
#
# Stage 1 (build): install dependencies with npm ci and build the Nuxt app.
# Stage 2 (runtime): slim image with only the built output, runs as the
# non-root `node` user. No dev dependencies, no source code in the runtime.
#
# Pinned versions (verified to exist on Docker Hub, 2026-11-24):
#   node:20.18.1-alpine

# ---------- Stage 1: build ----------
FROM node:20.18.1-alpine AS build

WORKDIR /app

# Install dependencies first (better layer caching).
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . ./
RUN npm run build

# ---------- Stage 2: runtime ----------
FROM node:20.18.1-alpine

# Apply latest Alpine security patches for the pinned base image (openssl, musl, zlib).
RUN apk update && apk upgrade --no-cache

# wget (busybox) is used by the container healthcheck.
WORKDIR /app

COPY --from=build --chown=node:node /app/.output /app/.output
COPY --chown=node:node package.json ./

ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000

USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
