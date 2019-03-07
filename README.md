# Latte syntax checker

This library helps to find erros in latte template files, it can be used in CI tools.

## Usage
`latte-syntax-checker check [-b|--bootstrap BOOTSTRAP] [-c|--compiled-dir COMPILED-DIR] [--] <dirs>...`

For more information, run `latte-syntax-checker check check --help`

The result looks like:

```
Errors found: 1
```

With verbose output you will get:
```
Errors found: 1

Error: Unknown macro {unknownmacro} in .../Test/default.latte:4
/var/www/test/app/Presenters/templates/Test/default.latte:4
```

And with very verbose there will be also part of file content with error.

Return code is count of errors found, so you can use it in CI tools.
```
echo $?
1
```

## Installation
Latte syntax checker requires PHP 7.1.0 or newer. You can install it via Composer. This project is not meant to be run as a dependency, so install it globally:

`composer global require efabrica/latte-syntax-checker`
