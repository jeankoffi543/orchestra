
[![Tests](https://github.com/jeankoffi543/orchestra/actions/workflows/tests.yml/badge.svg)](https://github.com/jeankoffi543/orchestra/actions)
![Packagist Version](https://img.shields.io/packagist/v/kjos/orchestra)
![PHP](https://img.shields.io/badge/PHP-%5E8.0-blue)
![License](https://img.shields.io/github/license/jeankoffi543/orchestra)


## 🌐 Multilangue | Multilingual

- 🇫🇷 [Version Française](README.fr.md)
- 🇬🇧 [English Version](README.md)

## 🇬🇧 English

# kjos orchestra
A powerful multi-tenancy manager for Laravel, with built-in support for domain isolation, database separation, and automated Apache virtual host configuration.

# Installation
`composer require kjos/orchestra`

## Master tenant installation
Before beginning you must install the master tanant. 
We will ask you to configure the `User` wo will use for virtuals hosts creation.
When `User` is configure, a shedule task is creating in your crontab table, you can type `crontab -e` to see it.
The shedule task will use your `User` with it privileges to automate the creation et configuration of your `virtualhost`.
`virtualhosts` are configured in /etc/apache2/sites-available directory.

# Concept
`kjos/orchestra` simplifies the management of multi-tenant applications by providing:

- Independent databases per tenant

- Separate route files, config, and storage per tenant

- Automatic setup of Apache virtual hosts

- Easy tenant lifecycle management via Artisan commands

It’s ideal for SaaS platforms or systems requiring strict tenant isolation.



# Getting Started

## Master Tenant Setup
Before adding tenants, you must install and configure the master tenant.

## What happens during setup?
You’ll configure a system User that will be used to manage Apache virtual hosts.

A scheduled task will be added to your crontab to automate virtual host creation.

Apache virtual hosts are stored in:
/etc/apache2/sites-available/

## Installation Steps
1. Create & configure the master tenant

2. Publish the config file:
```
php artisan vendor:publish --tag=orchestra-config
```
3. Create the database for the master tenant

4. Add the tenant to the .tenants file at your project root

5. Create the site/ directory, which will contain subfolders for each tenant

6. Link each tenant's /public directory to your Laravel public root

7. Configure the virtual host:

  - Symlink: /var/www/html/{tenant-domain} → project/site/{tenant-name}

  - Apache config: /etc/apache2/sites-available/{tenant-domain}.conf

  - Log file: /var/log/apache2/{tenant-domain}.log

8. Enable the virtual host:
```
sudo a2ensite {tenant-domain}.conf
sudo systemctl reload apache2
```
9. Register the service provider in config/app.php:
```
App\Providers\TenantServiceProvider::class,
```

The `TenantServiceProvider` is the central kernel that initializes and resolves tenant-specific context and configuration.


## Artisan Commands

### Install Master Tenant
`php artisan orchestra:install the_master_tenant_name --domain=master_tenant_domain --driver=[mysql|pgsql]`

### ❌ Uninstall Master Tenant
`php artisan orchestra:uninstall the_master_tenant_name --driver=[mysql|pgsql]`

### ➕ Create a New Tenant
`php artisan orchestra:create the_tenant_name --domain=the_tenant_domain --driver=[mysql|pgsql] --migrate`


### ➖ Delete a Tenant
`php artisan orchestra:delete the_tenant_name --driver=[mysql|pgsql]`


### 🧾 Available Options
| Option            | Description                                         |
|:-----------------:|:---------------------------------------------------:|
| `--domain`        | The domain of the tenant                            |      |
| `--driver`        | Database driver `pgsql` or `mysql`                  |
| `--migrate`       | Either migrate the fresh tenant database or not     |               |


###  Additional Features
Each tenant has its own:

- `routes` (API, web, console)

- `database`

- `storage` & `cache`

- `config` context

Crontab automation for managing virtual hosts

Support for Laravel testing with tenant isolation

Dynamic tenant discovery via `.tenants` file

.

# 📁 Directory Structure
```
project-root/
├── site/
│   ├── master/
│   │   └── routes/
│   └── tenant-a/
│   |   └── routes/
|   |__ tenant-b/
|       |__routes/
|
├── .tenants
├── config/orchestra.php
├── app/Providers/TenantServiceProvider.php
```

# 👤 Author
Maintained by [Jean Koffi](https://www.linkedin.com/in/konan-kan-jean-sylvain-koffi-39970399/)

# 📄 License
MIT © kjos/orchestra


# 🤝 Call for contributions
This project is open to contributions!
Are you a developer, passionate about Laravel, or interested in multi-tenant architecture?

- Fork the project

- Create a branch (koor/my-feature)

- Make a PR 🧪

There are many other command for additionally configuration.