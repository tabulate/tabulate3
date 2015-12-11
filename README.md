Tabulate
========

Tabulate is a user-friendly web interface to MySQL databases.

[![Build Status](https://img.shields.io/travis/tabulate/tabulate3.svg?style=flat-square)](https://travis-ci.org/tabulate/tabulate3)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/tabulate/tabulate3/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/tabulate/tabulate3/?branch=master)
[![License](https://img.shields.io/github/license/tabulate/tabulate3.svg?style=flat-square)](https://github.com/tabulate/tabulate3/blob/master/LICENSE.txt)

## Install

1. Clone from git: `git clone https://github.com/tabulate/tabulate3`
2. Update dependencies: `composer update`
3. Create configuration file: `cp config.dist.php config.php`
4. Edit configuration file
5. Run the upgrade script: `./tabulate upgrade`

## Backup

1. Run the backup script: `./tabulate backup /path/to/backup/directory/` (will create a single file named e.g. `tabulate_site_name_here_2015-01-01.tgz`)
2. Copy the backup file to somewhere safe.

## Restore

1. Run the restore script: `./tabulate restore /path/to/backup_file.tgz`

## Upgrade

1. Update dependencies: `composer update`
2. Run the upgrade script: `./tabulate upgrade`

## Features

1.  Tables can be filtered by any column or columns, and with a range of
    operators ('contains', 'is', 'empty', 'one of', 'greater than', 'less than',
    and the negations of all of these). Multiple filters are conjunctive
    (i.e. with a logical *and*).
2.  Access can be granted to *read*, *create*, *update*, *delete*, and *import*
    records in any or all tables. (This can be done by anyone with the
    *promote_users* capability.) Access can also be granted to *anonymous users*.
3.  CSV data can be imported, with the UI supporting column mapping, data
    validation, and previewing prior to final import. If an imported row has a
    value for the Primary Key, the existing row will be overwritten.
4.  Data can be exported to CSV, including after filters have been applied. 
6.  Records in tables with *date* columns can be viewed in a calendar.
7.  Entity Relationship Diagrams (drawn with [GraphViz](http://graphviz.org/))
    can be automatically generated, with any specified subset of tables. Foreign
    keys are displayed as directed edges.
8.  All data modifications are recorded, along with optional comments that users
    can provide when updating data.
9. Tables with *point* columns can be exported to KML and OpenStreetMap XML.
    Also, data entry for these columns is done with a small slippy map, on which
    a marker can be placed.

Development is managed on GitHub: https://github.com/tabulate/tabulate3
