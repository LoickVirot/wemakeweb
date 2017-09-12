We make web main website
========================

The We make web official website. Contains a collaborative blog, to share knowledge and projects to the web.
 
## Technology
We make web is running with with Symfony 3, a PHP framework.

## Install
Install we make web on your environment

### Requirements
- All [symfony requirements](http://symfony.com/doc/current/reference/requirements.html)

### Installation
- Clone repository
```bash
git@github.com:LoickVirot/wemakeweb.git
```

- Go to the directory and run composer
```bash
composer install 
```

- Generate database
```bash
php bin/console doctrine:database:create
```

Don't forget to setting up [cache permissions](https://symfony.com/doc/current/setup/file_permissions.html)

## Can I contribute ?
Yes ! All planned features are available to see in the [project page](https://github.com/LoickVirot/wemakeweb/projects). We will be graceful if you help us to develop one ! Just some rules :
- Comment your code
- Make your code easy to maintain
- Have fun !
