# Qualint

PHP Archaic Linter / Clean Code checker

**Warning: this code is not tested yet, please be cautious with its use**

## Installation

```bash
composer config repositories.repo-name vcs https://github.com/YonisSavary/Qualint
composer require yonissavary/qualint
```

## Usage

The basic usage is :
```bash
vendor/bin/qualint --verbose
```
It will tell you everything "wrong" with your code

If you want to see change in a safe way, then execute
```bash
vendor/bin/qualint --behavior=BACKUP --verbose
```
This will save the changes to original files but save the files original content
in backup files

Otherwise, if you are sure that changes won't break your code, execute
```bash
vendor/bin/qualint --behavior=OVERWRITE --verbose
```

For more see :
```bash
vendor/bin/qualint --help
```

## Using Qualint in your code (WIP)

You can also use the `Qualint` class to have a better control

```php

use YonisSavary\Qualint\Qualint;

$qualint = new Qualint(
    ["./badFile.php"],              // Files to analyse
    [fn($line) => print($line)],    // Log functions
    Qualint::BEHAVE_BACKUP          // Behave mode
);

$qualint->launch();
```