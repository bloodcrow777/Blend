## [0.9.10] - 2018-02-12

- Add getCurrentValue method for SystemSettings, this method returns the current value of the system setting before blend/save

## [0.9.10] - 2018-02-12

- Resources sorted by context directories
- Create resource groups if they do not exist add attach resource, no ACLs are created
- Improve ResourceTest

## [0.9.9] - 2018-02-10

- Add TemplateTVTest
- Fix TVs to seed and blend with elements data 
- Add related data to revert process, Template=>TVs now revert

## [0.9.8] - 2018-02-08

- Fix site migration template with proper method name

## [0.9.7] - 2018-02-08

 - Fix for not setting code/content on elements if they are set as static, overwrite is now an option
 - Finish matching the migration file name to the seeds directory name
 - Add Resource Groups to resource seeds
 - Refactor to so that seeds directory matches the name of the migration file
 - Refactor timestamp to seeds_dir
 - Added version info, author to Migrations and a refresh cache option
