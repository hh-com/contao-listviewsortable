# contao-listviewsortable

Sort dca list in Backend without child table

Make the list sortable:

``` code
$GLOBALS['TL_DCA']['tl_yourTable']['list']['sorting']['listViewSortable'] == true;
```

## Install

Copy to:  
root  
\- src  
\- - hh-com  
\- - - contao-listviewsortable  

Update your contao installation composer.json
``` code
"repositories": [
    {
        "type": "path",
        "url": "./src/hh-com/contao-listviewsortable",
        "options": {
                "symlink": true
        }
    }
],
"require": {
    ...
    "hh-com/contao-listviewsortable": "@dev",
    ... 
}
```
Run:

php -d memory_limit=-1 ./path/to/composer.phar update

php vendor/bin/contao-console cache:clear

Run php vendor/bin/contao-console contao:symlinks





THX to https://github.com/psi-4ward/listViewSortable