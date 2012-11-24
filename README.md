Generates payroll data and store in CSV file.

Usage: php payrol.php [OPTIONS]
-h  print this screen
-year   set the year for which you need the payrol to be generated. Defaults to the current year.
-file   file name to store the results to. You could specify a file name (payroll.csv) or path and file name (/data/files/payroll.csv). In either cases, PHP must have write permissions on the destination folder. Defaults to payroll.csv

Examples:

1. Default behaviour:<br />
php payroll.php

2. Specify an year, different from default (current) one:<br />
php payroll.php -year=2012

3. Specify file name (or path and name), different from the default one:<br />
php payroll.php -file=payroll-2012.csv<br />
php payroll.php -file=/docs/accounting/payroll-2012.csv

4. Specify both year and file name:<br />
php payroll.php -year=2012 -file=/docs/accounting/payroll-2012.csv