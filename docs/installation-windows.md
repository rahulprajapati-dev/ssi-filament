# 2) Install (Windows)

Clone and dependencies:
```powershell
git clone <repo-url>
cd .\multi-oem-dms\
composer install
npm install
```

Environment and app key:
```powershell
Copy-Item .env.example .env
php artisan key:generate
```

App database (SQLite):
```powershell
New-Item -ItemType File -Path .\database\database.sqlite -Force
```

Migrate app DB:
```powershell
php artisan migrate
```

Build assets:
```powershell
npm run dev   # dev with HMR
# or
npm run build # production build
```

Optional caches reset:
```powershell
php artisan optimize:clear
```