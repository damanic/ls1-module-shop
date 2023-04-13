<?php
class Shop_HsCodes {

    /**
     * The data file that contains The Harmonized Commodity Description and Coding System
     * Source: https://github.com/datasets/harmonized-system
     * @var string
     */
    private static $dataFile = '/modules/shop/resources/data/harmonized-system.csv';

    /**
     * The data loaded from the data file
     * @var array
     */
    protected static $dataSet = array();


    /**
     * Get the description of a HS Code
     * @param string $hsCode
     * @return false|mixed
     */
    public static function getHsCodeDescription($hsCode){
        $dataSet = self::getDataSet();
        if(isset($dataSet[$hsCode])){
            return $dataSet[$hsCode]['description'];
        }
        return false;
    }

    /**
     * List all HS Codes.
     * @param int $codeLength The code length to return, set to 0 to return all codes
     * @return array Array of HS Codes where HS Code is the key and description is the value
     */
    public static function listHsCodes($codeLength = 6) {
        $dataSet = self::getDataSet();
        $list = array();
        foreach($dataSet as $hsCode => $values){
            if(!$codeLength || strlen($hsCode) === $codeLength){
                $list[$hsCode] = $values['description'];
            }
        }
        return $list;
    }

    /**
     * Get the CSV data from the data file
     * @return array Array of CSV data fields
     */
    public static function getDataSet(){
        if(self::$dataSet){
            return self::$dataSet;
        }
        $csvData = array();
        $dataSet = array();
        $row = 0;
        if (($handle = fopen(PATH_APP.self::$dataFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                if($row === 1){
                    continue;
                }
                $section = $data[0];
                $hscode = $data[1];
                $description = $data[2];
                $parent = $data[3];
                $level = $data[4];
                $dataSet[$hscode] = [
                    'section' => $section,
                    'code' => $hscode,
                    'description' => $description,
                    'parent' => $parent,
                    'level' => $level,
                ];
            }
            fclose($handle);
        }

        return self::$dataSet = $dataSet;
    }

}