Tabulate
========

Tabulate is a user-friendly web interface to MySQL databases.

[![Build Status](https://img.shields.io/travis/tabulate/tabulate.svg?style=flat-square)](https://travis-ci.org/tabulate/tabulate)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/tabulate/tabulate/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/tabulate/tabulate/?branch=master)
[![License](https://img.shields.io/github/license/tabulate/tabulate.svg?style=flat-square)](https://github.com/tabulate/tabulate/blob/master/LICENSE.txt)

## Install

1. Clone from git: `git clone https://github.com/tabulate/tabulate3`
2. Update dependencies: `composer update`
3. Create configuration file: `cp config.dist.php config.php`
4. Edit configuration file
5. Run the upgrade script: `./tabulate upgrade`

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
5.  A quick-jump navigation box (located top right of every page) can be
    activated by installing the [WordPress REST API (Version 2)](https://wordpress.org/plugins/rest-api/)
    plugin. The quick-jump box is also added as a Dashboard widget.
6.  Records in tables with *date* columns can be viewed in a calendar.
7.  Entity Relationship Diagrams (drawn with [GraphViz](http://graphviz.org/))
    can be automatically generated, with any specified subset of tables. Foreign
    keys are displayed as directed edges. This feature is only available if the
    [TFO Graphviz plugin](https://wordpress.org/plugins/tfo-graphviz/) is installed.
8.  All data modifications are recorded, along with optional comments that users
    can provide when updating data.
9.  The `[tabulate]` shortcode can be used to embed tables, lists, row-counts,
    and data-entry forms into WordPress content. For more details, see the
    [FAQ section](https://wordpress.org/plugins/tabulate/faq/).
10. Tables with *point* columns can be exported to KML and OpenStreetMap XML.
    Also, data entry for these columns is done with a small slippy map, on which
    a marker can be placed.

Development is managed on GitHub: https://github.com/tabulate/tabulate

## Installation

### Installing

1. Follow the [usual plugin installation procedure](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).
2. To get a quick-jump navigation box, also install the
   [WP REST API](https://wordpress.org/plugins/json-rest-api/) plugin.
3. For Entity Relationship Diagram support, also install the
   [TFO Graphviz](https://wordpress.org/plugins/tfo-graphviz/) plugin.
4. Create some new database tables in your WordPress database, using a tool such
   as [PHPmyAdmin](http://www.phpmyadmin.net) or [MySQL Workbench](http://mysqlworkbench.org/).
5. Browse to the Tabulate overview page via the main menu in the WordPress admin
   interface.

### Upgrading

When upgrading, please *deactivate* and then *reactivate* the plugin. This will
ensure that all required database updates are carried out (but will avoid the
overhead of checking whether these are required on every Tabulate page load).

After version 2.0.0 you must switch to version 2 of the Rest API plugin (`rest-api`).
Remove the older one (`json-rest-api`).

## Frequently Asked Questions

### How does one use the shortcode?

A [Shortcode](http://codex.wordpress.org/Shortcode) is a WordPress method of
embedding content into posts and pages. Tabulate provides one short code, `[tabulate]`,
which can be used to add tables, lists, data-entry forms, and record-counts to
your content. Its parameters (which can appear in any order) are as follows:

1. `table` — The name of the table in question. Required. No default.
2. `format` — One of `table`, `list`, `form`, `count`, or `record`. Optional. Defaults to `table`.

Do note that if a table is not accessible to the browsing user then nothing will
be displayed.

When using the `record` format, the primary key of the record to display will be
taken from the URL parameter that is the table's name
(e.g. `[tabulate table=widgets format=record]` will look for `?widgets=45`
and display the record with a primary key value of `45`).

### Where should issues be reported?

Please log all bugs, feature requests, and other issues in the GitHub issue
tracker at https://github.com/tabulate/tabulate/issues

### What modifications does Tabulate make to the database?

Two database tables are created, and one [option](http://codex.wordpress.org/Option_Reference),
all prefixed with `tabulate_`. When Tabulate is uninstalled, all of these are
deleted (but custom tables are not touched).

### Is row-level access control possible?

This should be done by creating a [view](https://dev.mysql.com/doc/refman/5.1/en/create-view.html)
(of one or more tables) and granting access to that.

### What reasons exist for the 'failed to create *.csv' error?

If you are getting an error like "Failed to create C:\Windows\Temp\tabulate_5593a4c432a67.csv"
or "Failed to create /tmp/tabulate_5593a4c432a67.csv"
then you should

1. firstly check that your database user has the [FILE privilege](https://dev.mysql.com/doc/refman/5.7/en/privileges-provided.html#priv_file);
2. then make sure your web server user has write-access to the system temp directory;
3. and if those don't work, add the following to your `wp-config.php`:
   `define( 'WP_TEMP_DIR', ABSPATH . 'wp-content/tmp/' );` and create the `wp-content/tmp/` directory.

### Where is the developers' documentation?

For information about the development of Tabulate or integrating other plugins
with it please see
[CONTRIBUTING.md](https://github.com/tabulate/tabulate/blob/master/CONTRIBUTING.md#contributing).

## Screenshots

1. The main screen of a single table, with provision for searching and navigating.
2. The permission-granting interface. All roles are shown across the top, and
   all tables down the left side.

## Changelog

This is a reverse-chronologically ordered list of breaking changes to Tabulate.
A full list of all changes can be found at https://github.com/tabulate/tabulate/commits/master

* October 2015: Version 2, switching to version 2 of the WP-API plugin.
* July 2015: Version 1, with basic functionality and after having
  been run for some months in a production environment by the plugin author.
* March to July 2015: Pre-release development.

Prior to version 1, no changes were listed here (there were too many of them, and
nothing was stable yet).

## Upgrade Notice

No special action needs to be taken to upgrade. Tabulate can be deactivated and
reactivated without losing any data; if uninstalled, it will remove everything
that it's added (but you will be warned before this happens, don't worry).

No custom database tables are modified during upgrade, activation, deactivation,
or uninstallation.
