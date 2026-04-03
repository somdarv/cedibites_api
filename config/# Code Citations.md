# Code Citations

## License: MIT

https://github.com/mhzed/wstunnel/blob/736629a5a75aeaa81bc5b2391680425a8bff44b8/readme.md

````
Good — I have the full picture. Here's exactly what needs to happen:

---

## Complete Reverb Setup Guide

Everything is already wired in code. You just need **server configuration**.

### Step 1: Backend `.env` (on your Ubuntu server)

SSH into your server and edit your API's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=cedibites
REVERB_APP_KEY=cedibites-reverb-key
REVERB_APP_SECRET=cedibites-reverb-secret-change-me
REVERB_HOST=api.yourdomain.com
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
````

Replace `api.yourdomain.com` with your actual API domain.

### Step 2: Frontend `.env.local` (on your Next.js deployment)

```env
NEXT_PUBLIC_REVERB_APP_KEY=cedibites-reverb-key
NEXT_PUBLIC_REVERB_HOST=api.yourdomain.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

Port **443** because the client connects via your Nginx reverse proxy (HTTPS), not directly to 8080.

### Step 3: Start Reverb as a systemd service (Ubuntu server)

```bash
sudo nano /etc/systemd/system/reverb.service
```

Paste:

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/cedibites_api
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

### Step 4: Nginx — proxy WebSocket traffic

Add this to your API's Nginx server block (the one handling `api.yourdomain.com`):

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Reverb uses the

```


## License: MIT
https://github.com/mhzed/wstunnel/blob/736629a5a75aeaa81bc5b2391680425a8bff44b8/readme.md

```

Good — I have the full picture. Here's exactly what needs to happen:

---

## Complete Reverb Setup Guide

Everything is already wired in code. You just need **server configuration**.

### Step 1: Backend `.env` (on your Ubuntu server)

SSH into your server and edit your API's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=cedibites
REVERB_APP_KEY=cedibites-reverb-key
REVERB_APP_SECRET=cedibites-reverb-secret-change-me
REVERB_HOST=api.yourdomain.com
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

Replace `api.yourdomain.com` with your actual API domain.

### Step 2: Frontend `.env.local` (on your Next.js deployment)

```env
NEXT_PUBLIC_REVERB_APP_KEY=cedibites-reverb-key
NEXT_PUBLIC_REVERB_HOST=api.yourdomain.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

Port **443** because the client connects via your Nginx reverse proxy (HTTPS), not directly to 8080.

### Step 3: Start Reverb as a systemd service (Ubuntu server)

```bash
sudo nano /etc/systemd/system/reverb.service
```

Paste:

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/cedibites_api
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

### Step 4: Nginx — proxy WebSocket traffic

Add this to your API's Nginx server block (the one handling `api.yourdomain.com`):

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Reverb uses the

```


## License: MIT
https://github.com/mhzed/wstunnel/blob/736629a5a75aeaa81bc5b2391680425a8bff44b8/readme.md

```

Good — I have the full picture. Here's exactly what needs to happen:

---

## Complete Reverb Setup Guide

Everything is already wired in code. You just need **server configuration**.

### Step 1: Backend `.env` (on your Ubuntu server)

SSH into your server and edit your API's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=cedibites
REVERB_APP_KEY=cedibites-reverb-key
REVERB_APP_SECRET=cedibites-reverb-secret-change-me
REVERB_HOST=api.yourdomain.com
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

Replace `api.yourdomain.com` with your actual API domain.

### Step 2: Frontend `.env.local` (on your Next.js deployment)

```env
NEXT_PUBLIC_REVERB_APP_KEY=cedibites-reverb-key
NEXT_PUBLIC_REVERB_HOST=api.yourdomain.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

Port **443** because the client connects via your Nginx reverse proxy (HTTPS), not directly to 8080.

### Step 3: Start Reverb as a systemd service (Ubuntu server)

```bash
sudo nano /etc/systemd/system/reverb.service
```

Paste:

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/cedibites_api
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

### Step 4: Nginx — proxy WebSocket traffic

Add this to your API's Nginx server block (the one handling `api.yourdomain.com`):

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Reverb uses the

```


## License: MIT
https://github.com/mhzed/wstunnel/blob/736629a5a75aeaa81bc5b2391680425a8bff44b8/readme.md

```

Good — I have the full picture. Here's exactly what needs to happen:

---

## Complete Reverb Setup Guide

Everything is already wired in code. You just need **server configuration**.

### Step 1: Backend `.env` (on your Ubuntu server)

SSH into your server and edit your API's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=cedibites
REVERB_APP_KEY=cedibites-reverb-key
REVERB_APP_SECRET=cedibites-reverb-secret-change-me
REVERB_HOST=api.yourdomain.com
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

Replace `api.yourdomain.com` with your actual API domain.

### Step 2: Frontend `.env.local` (on your Next.js deployment)

```env
NEXT_PUBLIC_REVERB_APP_KEY=cedibites-reverb-key
NEXT_PUBLIC_REVERB_HOST=api.yourdomain.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

Port **443** because the client connects via your Nginx reverse proxy (HTTPS), not directly to 8080.

### Step 3: Start Reverb as a systemd service (Ubuntu server)

```bash
sudo nano /etc/systemd/system/reverb.service
```

Paste:

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/cedibites_api
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

### Step 4: Nginx — proxy WebSocket traffic

Add this to your API's Nginx server block (the one handling `api.yourdomain.com`):

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Reverb uses the

```


## License: MIT
https://github.com/mhzed/wstunnel/blob/736629a5a75aeaa81bc5b2391680425a8bff44b8/readme.md

```

Good — I have the full picture. Here's exactly what needs to happen:

---

## Complete Reverb Setup Guide

Everything is already wired in code. You just need **server configuration**.

### Step 1: Backend `.env` (on your Ubuntu server)

SSH into your server and edit your API's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=cedibites
REVERB_APP_KEY=cedibites-reverb-key
REVERB_APP_SECRET=cedibites-reverb-secret-change-me
REVERB_HOST=api.yourdomain.com
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

Replace `api.yourdomain.com` with your actual API domain.

### Step 2: Frontend `.env.local` (on your Next.js deployment)

```env
NEXT_PUBLIC_REVERB_APP_KEY=cedibites-reverb-key
NEXT_PUBLIC_REVERB_HOST=api.yourdomain.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

Port **443** because the client connects via your Nginx reverse proxy (HTTPS), not directly to 8080.

### Step 3: Start Reverb as a systemd service (Ubuntu server)

```bash
sudo nano /etc/systemd/system/reverb.service
```

Paste:

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/cedibites_api
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

### Step 4: Nginx — proxy WebSocket traffic

Add this to your API's Nginx server block (the one handling `api.yourdomain.com`):

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Reverb uses the

```


## License: MIT
https://github.com/mhzed/wstunnel/blob/736629a5a75aeaa81bc5b2391680425a8bff44b8/readme.md

```

Good — I have the full picture. Here's exactly what needs to happen:

---

## Complete Reverb Setup Guide

Everything is already wired in code. You just need **server configuration**.

### Step 1: Backend `.env` (on your Ubuntu server)

SSH into your server and edit your API's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=cedibites
REVERB_APP_KEY=cedibites-reverb-key
REVERB_APP_SECRET=cedibites-reverb-secret-change-me
REVERB_HOST=api.yourdomain.com
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

Replace `api.yourdomain.com` with your actual API domain.

### Step 2: Frontend `.env.local` (on your Next.js deployment)

```env
NEXT_PUBLIC_REVERB_APP_KEY=cedibites-reverb-key
NEXT_PUBLIC_REVERB_HOST=api.yourdomain.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

Port **443** because the client connects via your Nginx reverse proxy (HTTPS), not directly to 8080.

### Step 3: Start Reverb as a systemd service (Ubuntu server)

```bash
sudo nano /etc/systemd/system/reverb.service
```

Paste:

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/cedibites_api
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

### Step 4: Nginx — proxy WebSocket traffic

Add this to your API's Nginx server block (the one handling `api.yourdomain.com`):

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Reverb uses the

```

```
