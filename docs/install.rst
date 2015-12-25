Installing and upgrading
========================

Tabulate is designed to be as easy to install as possible (and, importantly, even easier to upgrade and backup).
It should run on most shared hosting environments, and installation is possible even for users with little technical knowledge.
Just be sure to follow the instructions closely!

The person responsible for installing and administering Tabulate is often more technically capable than the general users,
and should have no trouble following the command-line instructions below.
Some of the more advanced features will only be accessible with this method of installation.

However, a simpler (albeit sometimes slower) web-installer is also available for people with less experience.

Requirements
------------

First things first though: check that your hosting environment meets the following minimum requirements:

* MySQL
* PHP 5.5 or above
* Apache

For extra features, you will also need:

* GraphViz
* LaTeX
* PHP's LDAP extension

Web-based installation
----------------------

To install via SFTP or similar, follow this procedure:

1. Create a new MySQL database, and a user with ``ALL`` permissions on it
2. Download the latest release file from https://github.com/tabulate/tabulate3/releases
3. Unzip this file; it will create a directory named like ``tabulate_3.x.x``
4. Open the ``config.php`` file and edit it to include your database name and user's credentials
5. Upload the whole tabulate directory to a web-accessible location
6. Navigate to ``https://example.org/tabulate/install`` and follow the prompts

CLI
---

The basic steps to install Tabulate from the command line are as follows:

1. Clone the repository with Git: ``git clone https://github.com/tabulate/tabulate3``
2. Update dependencies (`get composer`_ if you don't already have it): ``composer update``
3. Create the configuration file: ``cp config.dist.php config.php`` and edit it to enter your database user credentials and other details
4. Run the upgrade script: ``./tabulate upgrade``

.. _`get composer`: http://getcomposer.org/

Finishing the installation
--------------------------

Once the basic installation procedure is complete,
you will be able to log in as the Administrator user with the username ``admin`` and the password ``admin``.
You should immediately change this password, and set your administrator user's email address.
