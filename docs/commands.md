# 9) Common Commands

First-time setup:
```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path .\database\database.sqlite -Force
php artisan migrate
```

Daily dev:
```powershell
npm run dev
php artisan serve
php artisan queue:listen --tries=1
```

Schema workflow (Audi example):
```powershell
php artisan schema:export stock --connection=audi
php artisan schema:diff stock audi --connection=audi
php artisan schema:merge stock audi
```

Maintenance:
```powershell
php artisan optimize:clear
php artisan route:list
php artisan about
php artisan test
```