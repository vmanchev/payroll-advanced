<?php
error_reporting(E_ERROR);

/**
 * Payroll schedule
 * 
 * Creates payroll schedule and store the data into CSV file. 
 * 
 * Rules:
 * 
 * - Sales staff get a regular monthly fixed base salary and a monthly bonus.
 * - The base salaries are paid on the last day of the month unless that day is 
 * a Saturday or a Sunday (weekend) in which case they are paid on the friday before.
 * - On the 15th of every month bonuses are paid for the previous month, unless 
 * that day is weekend. In that case, they are paid the first Wednesday after the 15th.
 * - The output of the utility should be a CSV file, containing the payment dates 
 * for the remainder of this year. The CSV file should contain a column for the 
 * month name, a column that contains the salary payment date for that month, and 
 * a column that contains the bonus payment date.
 * 
 * Basic usage: 
 * php payroll.php
 * 
 * For more information pass the help option:
 * php payroll.php -h
 * 
 * @package Payroll
 * @version 0.0.1
 * @author Venelin Manchev
 * @license CC-SA
 */
class Payroll {

    /**
     * Year, for which the payroll schedule should be generated. If not set, 
     * defaults to the current year. Acceptable range of values is 20 years before
     * and after the current year.
     * @var int
     */
    public $year;
    
    /**
     * File name, to which the output data should be written. Defaults to payroll.csv 
     * and the location is the working directory. Use the -file option to set 
     * another (location and) name. Location could be either absolute or relative. 
     * When using a relative location, always mind the current working directory.
     * @var string 
     */
    public $file;
    
    /**
     * Storage container for the payroll data. Each element contains array of 
     * values for a single row in the output file.
     * @var array
     */
    private $data = array();
    
    /**
     * Alternavive day for the base salary payday 
     */
    const PAY_DAY_ALT = "last friday";
    
    /**
     * Bonus payday 
     */
    const BONUS_DAY = 15;
    
    /**
     * Alternative day for the bonus payday 
     */
    const BONUS_ALT = "next wednesday";
    
    /**
     * Initialise the Payroll class
     * 
     * To change the default year and file name values, pass the relevant 
     * parameters to the class constructor.
     * 
     * @param int $year
     * @param string $file
     * @return \Payroll 
     */
    public function __construct($year = 0, $file = ''){
        
        //filter the year parameter
        $year = (int) $year; 

        //set the year value or output an error message
        try{
            $this->year = ($this->__isValidYear($year)) ? $year : date('Y');
        }catch(PayrollException $e){
            $this->__printError($e);
        }
        
        //set the new file name is one is passed
        if(!empty($file)){
            $this->file = $file;
        }
        
        //validate the file name and if its necessary - output an error message
        try{
            $this->__isValidFile();
        }catch(PayrollException $e){
            $this->__printError($e);
        }

        return $this;
    }
    
    /**
     * Generates payrol data
     * 
     * Populates the {@see Payrol::$data} array with payroll information.
     * 
     * @return \Payroll This method returns a reference to the class itself, so 
     * we could easily chain any other convinient methods, such as save, download, 
     * sendMail, etc.
     */
    public function generate(){

        //Loop over the months and collect the necessary data for each one of them.
        for($i = 1; $i < 13; $i++){
            $this->data[] = array(
                $this->__getMonthName($i),
                $this->__getPayDay($i),
                $this->__getBonusDay($i)
            );
        }
        
        return $this;
    }
    
    /**
     * Save data to the output file
     * 
     * Use the data from {@see Payroll::$data} to create the output CSV file. If 
     * the data is not available (in case this method is called before 
     * {@see Payroll::$generate}, an error message will be printed out.
     * 
     * @return \Payroll This method returns a reference to the class itself, so 
     * we could easily chain any other convinient methods, such as download, 
     * sendMail, etc.
     */
    public function save(){
        //check the $data status
        if(!empty($this->data)){
            
            //Create a file with the given file name. If the file exists, it'll 
            //be truncated.
            $fh = fopen($this->file, "w");

            //Write the title row
            fputcsv($fh, array("Month", "Salary", "Bonus"));            
            
            //Loop over the {@see Payroll::$data} values and write the rows.
            foreach($this->data as $row){
                fputcsv($fh, $row);
            }
            
            //close the file handler
            fclose($fh);
            
            return $this;
            
        }else{
            //If a developer don't follow the correct logic, and call the save()
            //method, before generate(), print an error message
            $this->__printError(new PayrollException("Can not save payroll as no data is available."));
        }
    }
    
    /**
     * Print an error message
     * 
     * Print an error message, using the strerr output stream. The message font 
     * colour is set to red.
     * @param PayrollException $e 
     */
    private function __printError(PayrollException $e){
        
        $stderr = fopen('php://stderr', 'w');
        fwrite($stderr, "\033[31m".$e->getMessage()."\033[37m".PHP_EOL);
        fclose($stderr);
        exit(0);        
        
    }
    
    /**
     * Validates a given year
     * 
     * To be valid, the passed value should be within the range of 20 years 
     * before and after the current year.
     * 
     * @param int $year
     * @return boolean
     * @throws PayrollException 
     */
    private function __isValidYear($year){
        if((date('Y') - 20) < $year && $year < date('Y') + 20){
            return true;
        }else{
            throw new PayrollException("Provided year value is out of range.");
        }
    }
    
    /**
     * Validates a file
     * 
     * Using the provided value for file name (or name AND path), make sure the 
     * destination directory is writable.
     * @return boolean
     * @throws PayrollException 
     */
    private function __isValidFile(){
        
        $paths = explode(DIRECTORY_SEPARATOR, $this->file);
        array_pop($paths);
        $path = implode(DIRECTORY_SEPARATOR, $paths);
        
        if(empty($path)){
            $path = './';
        }
        
        if(is_writable($path)){
            return true;
        }else{
            throw new PayrollException("Destination file and/or folder are not writable.");
        }
    }

    /**
     * Calculates the UNIX tampstamp for a given date
     * 
     * @param int $day
     * @param int $month
     * @return int UNIX timestamp
     */
    private function __toUnix($day, $month){
        return mktime(0,0,0,$month,$day,$this->year);
    }
    
    /**
     * Locates the week day for a given date
     * @param int $day
     * @param int $month
     * @return int 1 for Monday, 7 for Sunday
     */
    private function __getWeekDay($day, $month){
        return date('N', $this->__toUnix($day, $month, $this->year));
    }

    /**
     * Find the last day of the month. 
     * 
     * This is equal to the number of days in a given month.
     * 
     * @param int $month
     * @return int An intiger between 28 and 31
     */
    private function __getLastDay($month){
        return date('t', $this->__toUnix($month, 1, $this->year));
    }
    
    /**
     * Get the full name of a given month
     * 
     * @param int $month
     * @return string
     */
    private function __getMonthName($month){
        return date('F', $this->__toUnix(1, $month));
    }
    
    /**
     * Find the correct payday for a given month
     * 
     * @param int $month
     * @return string
     */
    private function __getPayDay($month){
	//get the last day in this moth
        $lastDay = date("t", $this->__toUnix(1, $month));

        //last day in UNIX timestamp (to avoid several additional calls later on)
        $lastDayUnix = $this->__toUnix($lastDay, $month);
        
	//which weekday is the last day of the month (1 - Mon, 7 - Sun)
	$lastDayWeek = date("N", $lastDayUnix);

        //is it during the weekend?
	if($lastDayWeek > 5){
            $payDayUnix = strtotime(Payroll::PAY_DAY_ALT, $lastDayUnix);
        }else{
            $payDayUnix = $lastDayUnix;
        }

	return date('d/m/Y', $payDayUnix);        
    }
    /**
     * Find the correct bonus pay day
     * @param int $month
     * @return string 
     */
    private function __getBonusDay($month){
        
        //bonus day in UNIX timestamp (to avoid several additional calls)
        $bonusDayUnix = $this->__toUnix(Payroll::BONUS_DAY, $month);
        
	//which week day is BONUS_DAY of this month?
	$bonusDayWeek = date("N", $bonusDayUnix);

        //is it during the weekend?
	if($bonusDayWeek > 5){
            $bonusDay = strtotime(Payroll::BONUS_ALT, $bonusDayUnix);
	}else{
            $bonusDay = $bonusDayUnix;
	}

	return date('d/m/Y', $bonusDay);
    }

    /**
     * Provides help information
     * To see this text, use the -h option, for example:
     * php pauroll.php -h
     * @return string 
     */
    public static function help(){
        return 
"Generates payroll data and store in CSV file.

Usage: php payrol.php [OPTIONS]
-h\tprint this screen
-year\tset the year for which you need the payrol to be generated. Defaults to the current year.
-file\tfile name to store the results to. You could specify a file name (payroll.csv) or path and file name (/data/files/payroll.csv). In either cases, PHP must have write permissions on the destination folder. Defaults to payroll.csv

Examples:

1. Default behaviour:
php payroll.php

2. Specify an year, different from default (current) one:
php payroll.php -year=2012

3. Specify file name (or path and name), different from the default one:
php payroll.php -file=payroll-2012.csv
php payroll.php -file=/docs/accounting/payroll-2012.csv

4. Specify both year and file name:
php payroll.php -year=2012 -file=/docs/accounting/payroll-2012.csv\n";    
    }
}

/**
 * Custom exception class 
 */
class PayrollException extends Exception {};

/**
 * Set default values for CLI arguments
 */

//Year, for which the payroll data to be generated
$year = date('Y');

//File name, where the data should be stored. 
//If the file exists, it will be overridden.
$file = 'payroll.csv';

//Check for passed CLI arguments
if(count($argv) > 1){
    
    //Display help information and exit
    if($argv[1] == '-h' || $argv[1] == '-help'){
        $stdout = fopen('php://stdout', 'w');
        fwrite($stdout, Payroll::help());
        fclose($stdout);
        exit(0);
        
    }else{
        //User has passed some of the optional arguments.
        for($i = 1; $i < $argc; $i++){
            $arg = explode('=', $argv[$i]);
            $arg[0] = str_replace('-', '', $arg[0]);
            $$arg[0] = $arg[1];
        }
    }
}

//Filter input data
$year = (int) $year;
$file = trim(strip_tags($file));

//Initialise the object
$PayrollObj = new Payroll($year, $file);

//Call to generate the data and save the file
$PayrollObj->generate()->save();


