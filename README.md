DevOps Tool: Core
====================================

This module offers common core functionality for the  
[Robofirm DevOps Tool](https://bitbucket.org/robofirm/robofirm-devops).


You should create a project with this command:

```bash
composer create-project zendframework/zend-expressive-skeleton mydevopstool
```

Run `./mydevopstool/vendor/bin/devops` with no arguments to see all available commands. We recommend add `bin` to your path

Below are a few of the most common DevOps Tool modules we suggest:

1. [Application Orchestration](https://bitbucket.org/robofirm/devops-application-orchestration) - Application 
   installation, configuration, backups, builds, code deployments, maintenance mode, syncing of environments.
2. [Project Setup](https://bitbucket.org/robofirm/devops-project-setup) - Creation of initial project resources
   including Jira project, Jira user groups, pre-populated Confluence space, pre-populated 
   Bitbucket repositories for Terraform, Puppet, and App Setup.

The DevOps Tool supports many platforms. Here are a few common platforms:

* [Magento 2](https://bitbucket.org/robofirm/devops-magento-2-platform-support)
* [Magento 1](https://bitbucket.org/robofirm/devops-magento-1-platform-support)
* [Drupal](https://bitbucket.org/robofirm/devops-drupal-platform-support)
* [WordPress](https://bitbucket.org/robofirm/devops-wordpress-platform-support)

The DevOps Tool can interact with a number of different filesystems. Here are a few common ones:

* [AWS](https://bitbucket.org/robofirm/devops-aws-filesystem-support)
* [Azure](https://bitbucket.org/robofirm/devops-azure-filesystem-support)

The DevOps Tool currently only supports MySQL, but may support others in the future:

* [MySQL](https://bitbucket.org/robofirm/devops-mysql-database-support)
