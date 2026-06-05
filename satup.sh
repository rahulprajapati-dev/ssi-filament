
php artisan optimize:clear
php artisan migrate
php artisan db:seed
php artisan filament:install --panels


php artisan make:model ModuleBuilder -m
php artisan make:filament-resource ModuleBuilder

php artisan make:model FieldBuilder -m
php artisan make:filament-resource FieldBuilder

php artisan make:model LayoutBuilder -m
php artisan make:filament-resource LayoutBuilder


php artisan make:model Module -m
php artisan make:filament-resource Module

php artisan make:model ModuleField -m
php artisan make:filament-resource ModuleField

php artisan make:model ModuleLayout -m
php artisan make:filament-resource ModuleLayout