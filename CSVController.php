<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/models/CSVModel.php');



if(isset($_GET['slug'])){

    $csv = new CSVCLass;

    $csv->getCityData($_GET['slug']);

}


Class CSVClass extends CSVModel{

    private $_init_file = array();

    private $_file = array();

    private $_db_table = '`points`';

    private $_db_data = array();

    private $_new_file_data = array();

    private $_old_file_data = array();

    private $_fields= array();

    private $_attentions = array();

    private $_checks_array = array();

    private $_check_patterns = array();


    private $_opt = array();

    private $_db;

    function __construct(){ 
        
        /**
         * Set Pattern checks into $checks_array::
         * 
         * Address-city check pattern: 'addr';
         * 
         * Points check pattern: 'point';
         * 
         * Points check pattern: 'phone';
         */

        $this->_checks_array = ['addr', 'point', 'phone_site'];

        $this->_check_patterns= [

            'addr'=>['city','street_type','street','house','apartment'],

            'point'=>['lng','lat'],

            'phone_site'=>['phone','site']
        ];

        /**
         * Set $_GET Data to $this->_opt
         */
        
        $this->setOptsFromGET();

        /**
         * Load CSV file Data into $this->_file
         */

        $this->readCSVfile();

        /**
         * Copy initial CSV file Data into $this->_init_file
         */

        $this->_init_file = $this->_file;

        /**
         * Initialize DB Class
         */

        $this->_db = new CSVModel; 
       
    }

    public function getOpts(){

        return $this->_opt;

    }

    public function processCSVFile(){   

                
        echo '<span class="info-info"><h3><b>Prosessing CSV file to DB table ::</b></h3></span>';
        
        /**
         * Save to DB without Checks
         */

        if(isset($this->_opt['save']) && $this->_opt['save'] == 2){

            echo '<br><br>Saving to DataBase <b>without Deleting</b> repeating Data...<br><br>';
        
            return;
        
        } 

        /**
         * Process repeats search
         */

        $coinsidence_patterns = array(); 
        
        foreach($this->_checks_array as $check_pattern){

            $coinsidence_patterns[$check_pattern] = $this->findDataRepeats($this->_file, $check_pattern);         

        } 
             
        
        /**
         * Show Repeats Initial log
         */        
        
        if(!isset($this->_opt['promise'])){
                
            if(isset($this->_opt['show_reps']) && $this->_opt['show_reps'] == 1){ 
                
                /**  getMarkupRows "show" argument tells that repeats attention warning MUST be shown*/

                $markup_rows = $this->getMarkupRows('show');  
                                
                $this->printData($this->_file,['mode'=>'show', 'start'=>0, 'markup_rows'=>$markup_rows]);
                
            }
        }


        /**
         * Process CSV to DB Data
         */
               
        if(isset($this->_opt['save']) && $this->_opt['save'] == 1){ 
            
            if(!isset($this->_opt['promise'])){

                echo '<br><br><span class="info-info"><b>Initial CSV file:</b></span> '.count($this->_file).' rows';

                /**  getMarkupRows "off" argument tells that NO repeats attention warning must be shown*/

                $markup_rows = $this->getMarkupRows('off');

                // echo '<pre>';
                // print_r($markup_rows);
                // echo '</pre>';
               
                $this->printData($this->_file,['mode'=>'show', 'start'=>0, 'markup_rows'=>$markup_rows]);

            }

            foreach($this->_checks_array as $check_pattern){ 
                              
                $deleteFeedback = $this->deleteNonUniqueData($coinsidence_patterns[$check_pattern], $check_pattern);                

                if(!isset($this->_opt['promise'])){

                    if($deleteFeedback){
        
                        echo '<hr><h3><span class="warning-info">Checking Pattern "'.$deleteFeedback['c_pattern'].'"</h3></span>';

                        echo '<span class="info-info"><b>Initially detected</b> repeated rows: '.$this->printMarkupRowNum($check_pattern, $markup_rows).'</span>';
                        
            
                        if($deleteFeedback['rows_deleted']){                           
                            
                            echo '<br><br><span class="warning-info"><b>Currently deleted pattern(s):</b> '.$deleteFeedback['compared_row'].' :</span>:: <b>unset: '.$deleteFeedback['rows_deleted']. ' row(s) : rows №: '.substr($deleteFeedback['rows_log'],0,strlen($deleteFeedback['rows_log'])-1).'</b><br><br>';

                            $rows_to_print = explode(',',substr($deleteFeedback['rows_log'],0,strlen($deleteFeedback['rows_log'])-1));

                            $this->printDataRows($rows_to_print,$this->_init_file[$deleteFeedback['deleted_row']], ['row_num'=>$rows_to_print, 'row_style'=>'deleted', 'table_header'=>'common']);

                            echo '<br><span class="info-info"><b>Optimized CSV file:</b></span> '.count($this->_file).' rows<br><br>';
                            
                        } 

                        else{

                            echo "<br><br><span class='success-info'><b>No repeated rows occured</b> OR had been deleted (if existed) in previous checks</span><br><br>";
                        }

                        $markup_rows = $this->getMarkupRows('off'); 
                                                        
                        $this->printData($this->_file,['mode'=>'show', 'start'=>0, 'markup_rows'=>$markup_rows, 'patt_type'=>$check_pattern]);
                    
                    }
                    
                }

                $this->_file = $this->rearrangeArray($this->_file);  
            } 
            

            /* Get DB Data and count DB Data */

            $this->_db_data = $this->_db->getDBData();

            /**
             * Count CSV and DB rows
             */
            
            $db_rows_count = count($this->_db_data);           

            $csv_rows_count = count($this->_file); 
            
           
            if(!isset($this->_opt['promise'])){
                
                echo '<hr><br><span class="info-info"><b>Data statistics:</b></span> <br> CSV data contains '.$csv_rows_count.' rows <br> DB contains: '.$db_rows_count.' rows';

                    /* If Db Data contains MORE rows that CSV Data - stop file proceeding */
                
                if($db_rows_count > $csv_rows_count){

                    $this->showCSVlessRowsWarning();   
                    
                    return;
                }
            }

            $new_CSV_data_count = $csv_rows_count - $db_rows_count;

            $csv_start_row = $csv_rows_count - $new_CSV_data_count;

            $csv_end_row = $csv_rows_count - 1; 
            
            $this->separateCSVData($csv_start_row); 
            
            if(!isset($this->_opt['promise'])){

                echo '<hr><h4>New CSV data contains: '.$new_CSV_data_count.' rows</h4>'; 

                echo '<h4>New data List: ';

                $this->printData($this->_new_file_data,['mode'=>'show']);

                echo '</h4>';

                /**
                 * Show "Perform Save to DB" button 
                 * if CSV file contains New Data
                 */
                
                if($this->_new_file_data){
                    
                    echo '<form method="post" action="/data_checker.php?save=1&flush=1&promise=1">
                    <input type="submit" class="btn btn-success" value="Perform Save to DB"/>
                    </form>';
                }

            }
                       

            /* Compare Old CSV Data with DB Data (always Old) */

            $nonidents_array = $this->checkArraysOnIdentity($this->_old_file_data, $this->_db_data);
            
            if(!isset($this->_opt['promise'])){

                echo '<hr><h4>Old data List: </h4>'; 
            
            }           
             
            /* If Old CSV Data and DB Data are identical -> try to flush New CSV Data to DB */

            if(isset($this->_opt['flush']) && $this->_opt['flush'] == 1){

                if(empty($nonidents_array)){ 
                
                    /* Check if CSV file has New Data */    

                    if($this->_new_file_data){                        
   
                        /* Flush New CSV Data into DB */
                    
                        $this->flushToDB();
                        
                        $this->printData($this->_new_file_data,['mode'=>'show']);

                    } 
                    
                }

                else{

                    echo '<br><br><span class="info-info">CSV file has NO exceeding Data. Nothing to add to DB</span>';                    
                }

            }

            if(!isset($this->_opt['promise'])){

                if(!empty($nonidents_array)){

                    echo '<span class="warning-info"><h4>DB Update Required!</h4></span>';
                }  
                                               
                $this->printData($this->_old_file_data,['mode'=>'update', 'data'=>$nonidents_array, 'no_rows_markup'=>true, 'markup_rows'=>$this->getMarkupRows('off')]);

            }
   

            /* Show preUpdate Dialog (no Update process performed)*/
            if($this->preUpdateRequirements()){                

                $this->preUpdateDialog();                 

            }

            /* Perform Update (flush DB)*/
            if($this->flushUpdateRequirements()){                

                $this->flushUpdate();

                $nonidents_array = $this->checkArraysOnIdentity($this->_file, $this->_db_data);

                if(!isset($this->_opt['promise'])){

                    $this->printData($this->_old_file_data,['mode'=>'update', 'data'=>$nonidents_array]);

                }
            }

            /*  Consistency check: 
                *   CSV file should be absolutely identical to DB content 
                */


                if(!isset($this->_opt['promise'])){

                    $final_check_array = $this->runConsistencyCheck();

                    if(empty($final_check_array)){
        
                        echo '<hr><h4><span class="success-info">Final check:CSV Data is absolutely identical to DB</span></h4>';
                    }
        
                    else{
        
                        echo '<br><br><span class="danger-info">CSV Data is NOT identical to DB Data. Please, Update your Data</span>';
                    }
        
                }

                echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';
                
        }
        
    }

    private function getMarkupRows($mode){         
        
        foreach($this->_checks_array as $check_pattern){

            if(!empty($check_pattern)){

                $markup_rows[$check_pattern] = $this->printAttentions($check_pattern, $mode);
            }             
                      
        }  
       
        /* Placing Reps rows into $reps_rows Array */

        foreach($markup_rows as $markup_key=>$markup_value){ 
            
            if(!empty($markup_value)){

                foreach($markup_value as  $item){                    

                    $temp_rows = explode(',',$item);

                    $all_markup_rows[$markup_key][] = $temp_rows;

                }  
                
            }
        }

        return $all_markup_rows;

    }


    private function printDataRows(array $rows_to_print, array $source_array, array $params){

        // echo '<pre>';
        // print_r($params);
        // echo'</pre>';

        $row_count = 0;

        $row_nums = $params['row_num'];

        foreach($rows_to_print as $row_to_print){

            if(isset($params['table_header']) &&  $params['table_header'] === 'common'){

                if( $row_count == 0){                   

                    $params['first_row'] =  $params['row_num'][$row_count]; 
                }   
                
            } 
            
            $params['row_num'] =   $row_nums[$row_count];                     

            $this->printDataRow($source_array, $params);
            
            $row_count++;
        }  
    }

    private function printMarkupRowNum(string $pattern, array $markup_rows){        

        $rep_string="";        

        $markup_rows = $this->getMarkupRows('off');

        if(array_key_exists($pattern,$markup_rows)){

            foreach($markup_rows[$pattern] as $markup_rows){

                foreach($markup_rows as $markup_key=>$markup_row){

                    if($markup_key >= count($markup_rows)-1){

                        $rep_string .= $markup_row;
                    }

                    else{

                        $rep_string .= $markup_row.",";
                    }
                    
                    
                }

                $rep_string .= ' : ';
            }

        return substr($rep_string,0,strlen($rep_string)-3);

        }

                        
    }

    private function flushToDB(){

        if($rows_count = $this->_db->CSVtoDB($this->_new_file_data)){

            echo '<br><br><span class="success-info">Successfully added to DB: '.$rows_count.' rows</span>';

        }

        else{

            echo '<br><br><span class="danger-info">Cant perform CSVtoDB operation</span>';
        }
    }

    private function showCSVlessRowsWarning(){

        echo '<br><br><span class="danger-info">CSV file contains LESS ROWS than DB Data. File proceeding ABORTED! 
        <br>Please, check your CSV file</span>';

        $nonidents_array = $this->checkArraysOnIdentity($this->_db_data,$this->_file);

        echo '<hr><span class="warning-info">First non-identical row in DB: </span>'; 
        
        if(!empty($nonidents_array)){

            $this->printFirstNonIdentRow($nonidents_array);                        

        }
        else{

            echo '<span >No non-identical rows found in CSV - DB. <br>Probably, DB contains extra rows omitted in CSV. Check it out!</span>'; 

        }               
        

        echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';
       

    }

    private function preUpdateRequirements(){

        if(isset($this->_opt['save']) && $this->_opt['save'] == 1 && isset($this->_opt['show_reps']) && $this->_opt['show_reps']==0 && isset($this->_opt['update_id'])){

            return true;
        }

        return false;
    }

    private function preUpdateDialog(){

        echo '<br><br><span class="info-info">Updating DB row: '.$this->_opt['update_id'].'</span>';

        $this->_db_data = $this->_db->getDBData($this->_opt['update_id']);            

        echo '<br><br><span class="warning-info">Ready to update DB row: </span>';
        $this->printDataRow($this->_db_data[$this->_opt['update_id']], ['row_style'=>'updated','first_row'=>$this->_opt['update_id'],'row_num'=>$this->_opt['update_id'],'exclude_keys'=>['id']]);                   
        
        echo '<br><span class="warning-info"> --> to CSV row</span>: '.$this->_opt['update_id'];

        $this->printDataRow($this->_old_file_data[$this->_opt['update_id']], ['row_style'=>'replace','first_row'=>$this->_opt['update_id'],'row_num'=>$this->_opt['update_id']]);                   

        echo '<form method="post" action="/data_checker.php?save=1&update=2&promise=1&csv_id='.$this->_opt['update_id'].'&db_id='.$this->_db_data[$this->_opt['update_id']]['id'].'">
        <br><br><input type="submit" class="btn btn-warning" value="Perform Update DB"/>
        </form>';

        return;
    }

    private function runConsistencyCheck(){

        $this->_db_data = $this->_db->getDBData();
                        
        $final_check_array = $this->checkArraysOnIdentity($this->_file, $this->_db_data);  
        
        return $final_check_array;    
                
    }

    private function flushUpdateRequirements(){

        if(isset($this->_opt['update']) && $this->_opt['update']==2 && isset($this->_opt['csv_id']) && isset($this->_opt['db_id'])){

            return true;
        }

        return false;
    }

    private function flushUpdate(){

        $params = $this->_old_file_data[$this->_opt['csv_id']];

        $rows_count = $this->_db->updateDBRow($this->_db_table,$params, $this->_opt['db_id']);

        if(!empty($rows_count)){

            echo '<span class="success-info">DB row:'.$rows_count.' updated successfully</span>'; 
            
            $new_row = $this->_db->getDBDataRow($this->_opt['db_id']);
               

            foreach($new_row as $row){

                foreach($row as $item_key=>$item_value){

                    $output_arr[$item_key]=$item_value;
                }
                
            }

            $this->printDataRow($output_arr, ['row_style'=>'replace','first_row'=>$this->_opt['db_id'],'row_num'=>$this->_opt['db_id']]);
            
        }

        else{

            echo '<span class="warning-info">No rows affected. Check your Data or DB Connection</span>';
        }  
        
        echo '<br><br><a href="/data_checker.php?save=1&show_reps=0" class="btn btn-success"> Back to Optimizer </a>';

        return;
    }

    private function printFirstNonIdentRow($nonidents_array){

        $tmp = array_shift($nonidents_array);             
                            
            $this->printDataRow( $tmp, ['row_style'=>'deleted','first_row'=>0,'row_num'=>0]);
    }

    
    private function printData(array $data, array $params){

        // echo '<pre>';
        // print_r($data);
        // echo'</pre>';


        if(!isset($params['markup_rows'])){

            $markup_row_keys = array();
        }
        
        else{

            $markup_row_keys = $params['markup_rows'];
        }



        /** Get Rows Markup Table */

        if(isset($params['patt_type'])){

            $rows_markup_array =  $this->getRowsTypeMarkupArray($data,$markup_row_keys, $params['patt_type']);

        }

        else{
            
            $rows_markup_array =  $this->getRowsMarkupArray($data,$markup_row_keys);

        }
        
        
        /** Setting to  $data[$row_key]['Update'] Update button code of 'No change' label */
      
        if(!empty($data) && !empty($rows_markup_array)){
           
            if($params['mode'] == 'update'){            
            
                foreach($data as $row_key=>$row_values){
                    
                    $data[$row_key]['Update'] = '<span class="success-info">No change</span>';                    

                    if(isset($params['data'][$row_key]) && !empty($params['data'][$row_key])){                        
    
                        $data[$row_key]['Update'] = '<form method="post" action="/data_checker.php?save=1&show_reps=0&update_id='.$row_key.'&promise=1"><input type="submit" class="btn btn-light" value="Update DB"/></form>';
                                            
                    }                  
                }

            }            

            if($data[0]){

                echo '<table class="print_data_table">';

                echo '<tr>';

                /* Set up Table row number column */

                echo '<td> № </td>';

                /* Retrieving Table Head */

                foreach($data[0] as $key=>$value){             

                    echo '<td>'.$key.'</td>';                
                    
                }          
                echo '</tr>';


                /* Retrieving Table Body */ 
                
                $row_num = 0;

                foreach($data as $key=>$row){                  
                       

                    /* Check if should markup updated row */                    

                    if($row_type = $this->checkRowMarkup($row_num,$rows_markup_array)) {
                       
                        /**
                         * if printData func was called with params['no_rows_markup'] - no row markup applied
                         */

                        if(isset($params['no_rows_markup']) && $params['no_rows_markup'] === true){$row_type['type'] = "default";}

                        switch($row_type['type']){

                            case "row_first_num":

                             echo '<tr style="background:rgba(0,128,0,'.(0.3*$row_type['count']).');">';

                             break;

                             case "row_second_num":

                             echo '<tr style="background:rgba(255,0,0,'.(0.3*$row_type['count']).');">';

                             break;                             

                             default:

                             echo '<tr>';

                        }
                       
                        // echo '<pre>';
                        // print_r($row_type);
                        // echo'</pre>';                                              
                    
                    } 
                    

                    /* Start rows number from 0 or 1 depending on $params['start'] */                 
                                        
                    echo(!isset($params['start']))?'<td>'.($row_num + 1).'</td>':'<td>'.$row_num.'</td>'; 
                                    
                    foreach($row as $value){                        
                        
                        echo '<td>'.$value.'</td>';                                                          
                                
                    }
                    
                    $row_num++;

                    echo '</tr>';
                }          
                
                echo '</table>';

            }

        }
        
        else{

            echo '<span class="success-info">No data to print</span>';
        }
        
    }

    private function checkRowMarkup(int $row_num,array $rows_markup_array){

        $row_type = array();

        $type_count = 0;

        foreach($rows_markup_array as $rows_markup_pattern){

            foreach($rows_markup_pattern as $rows_markup_type=>$rows_markup_vals){

                foreach($rows_markup_vals as $row_val){

                    if($row_num == $row_val){

                        $type_count++;                        

                        $row_type=['type'=>$rows_markup_type, 'count'=>$type_count];
                       
                    }
                }
            }
        }

        return $row_type;
       
    }


    private function getRowsTypeMarkupArray(array $data,array $markup_row_keys, string $type){       

        $row_nums = array_keys($data);
        
        if(array_key_exists($type, $markup_row_keys)){

            $markup_type_row_keys[$type] = $markup_row_keys[$type];
        
        // echo '<pre>';
        // print_r($markup_type_row_keys);
        // echo'</pre>';        

            $markup_row = array();

            foreach($row_nums as $row_num){

                foreach($markup_type_row_keys as $patt_key=>$patt_row){ 
                
                    //echo '<br>Checking pattern: '. $patt_key.' for ROW NUM: '.$row_num;               
                    
        
                    foreach($patt_row as $row){

                        $perPattern_rows_count = 0;
        
                        foreach($row as $item){
        
                            if($row_num == $item && strlen($item)){
                            
                                if($perPattern_rows_count == 0){
            
                                    //echo '<br>perPattern_rows_count: '.$perPattern_rows_count;
            
                                    //echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: MARK FIRST ROW';
            
                                    $markup_row[$patt_key]['row_first_num'][] = $row_num;                           
            
                                } 
                                
                                else{
        
                                // echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: ELSE ROW';
                                
                                $markup_row[$patt_key]['row_second_num'][] = $row_num;  
                                }                            
                                                    
                            }
        
                            $perPattern_rows_count++;
            
                        }
                    }  
        
                }
            }

            return ($markup_row)? $markup_row:false;
        }
        
    }



    private function getRowsMarkupArray(array $data,array $markup_row_keys){       

        $row_nums = array_keys($data);  

        // echo '<pre>';
        // print_r($markup_row_keys);
        // echo'</pre>';
        

        $markup_row = array();

        foreach($row_nums as $row_num){

            foreach($markup_row_keys as $patt_key=>$patt_row){ 
            
                //echo '<br>Checking pattern: '. $patt_key.' for ROW NUM: '.$row_num;               
                
    
                foreach($patt_row as $row){

                    $perPattern_rows_count = 0;
    
                    foreach($row as $item){
    
                        if($row_num == $item && strlen($item)){
                        
                            if($perPattern_rows_count == 0){
        
                                //echo '<br>perPattern_rows_count: '.$perPattern_rows_count;
        
                                //echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: MARK FIRST ROW';
        
                                $markup_row[$patt_key]['row_first_num'][] = $row_num;                           
        
                            } 
                            
                            else{
    
                               // echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: ELSE ROW';
                            
                               $markup_row[$patt_key]['row_second_num'][] = $row_num;  
                            }                            
                                                   
                        }
    
                        $perPattern_rows_count++;
        
                    }
                }  
    
            }

        }
                     
        
        return ($markup_row)? $markup_row:false;

    }


    private function printDataRow(array $data, array $params){  
        
        // echo '<pre>';
        // print_r($params);
        // echo'</pre>';
      

        if($data){          

            echo '<table class="print_data_table">'; 

            /**
             * Printing Row table Header
             */

            /* If exists - Add row_num to the Table */
            if(isset($params['first_row']) && $params['first_row'] === $params['row_num']){

                /* Retrieving Table Head */
                echo '<tr>';

                echo (isset($params['row_num']))?'<td> № </td>':'';           

                foreach($data as $key=>$value){             

                    /**
                    * Apply $params['exclude_keys'] - exclude printing fields set in $params['exclude_keys']
                    */

                    if(isset($params['exclude_keys'])){

                        echo(!$this->checkExcludedKeys($key, $params['exclude_keys']))?'<td>'.$key.'</td>':'';
                    
                    }

                    else{

                        echo '<td>'.$key.'</td>';
                    }                 
                    
                }          
                echo '</tr>';

            }

            else{

                /* Retrieving Hidden Table Head */
                echo '<tr style="visibility:hidden; line-height:1px; padding:0px">';

                echo (isset($params['row_num']))?'<td style="padding:0px 5px"> № </td>':'';           

                foreach($data as $key=>$value){  
                               

                    echo '<td style="padding:0px 5px">'.$key.'</td>';                
                    
                }          
                echo '</tr>';


            }
            
            /** 
             * Applying Row style 
             */
            
             if(isset($params['row_style'])){

                switch($params['row_style']){

                    case 'deleted':

                    echo'<tr style="background:rgba(255,0,0,0.3)">'; 

                    break;

                    case 'updated':

                    echo'<tr style="background:rgba(255,69,0,0.3)">';

                    break;

                    case 'replace':

                    echo'<tr style="background:rgba(0,255,127,0.3)">';

                    break;
                }
             }             
             
             /* If exists - Add row_num to the Table */           

            echo (isset($params['row_num']))?'<td>'.$params['row_num'].'</td>':'';


             /* Retrieving Table Body */
            foreach($data as $key=>$row){

                /**
                 * Apply $params['exclude_keys'] - exclude printing fields set in $params['exclude_keys']
                 */

                if(isset($params['exclude_keys'])){

                    echo(!$this->checkExcludedKeys($key, $params['exclude_keys']))?'<td>'.$row.'</td>':'';
                  
                }

                else{

                    echo '<td>'.$row.'</td>';
                }               
                
                                      
            } 
            
            echo '</tr>';
            
            echo '</table>';

        }
        
        else{

            echo '<span class="success-info">No data to print</span>';
        }
        
    }

    private function checkExcludedKeys(string $key, array $ex_keys){

        if(isset($ex_keys) && !empty($ex_keys)){ 

            foreach($ex_keys as $ex_key){

                if($key == $ex_key){

                    return true;
                }
            }

        return false;
        }

    }

    private function checkArraysOnIdentity(array $test_array, array $etalon){ 

        $nonidents_array = array();
        
        for($row_count = 0; $row_count < count($etalon); $row_count++){

            $test_ar_pattern = $this->getIdentityPattern($test_array[$row_count]);

            //echo '<br>CSV pattern: '.$test_ar_pattern;

            $etalon_pattern = $this->getIdentityPattern($etalon[$row_count]);

            // echo '<br>DB pattern: '.$etalon_pattern;

            if($test_ar_pattern != $etalon_pattern){
            
                $nonidents_array[$row_count] = $test_array[$row_count];

                // echo '<br>ROW NOT IDENTICAL: '.$etalon_pattern;
            }
        }        

        return $nonidents_array;
      
    }

    private function getIdentityPattern(array $data){

        $pattern = '';

        foreach($data as $key=>$value){

            if($key != 'id'){

                $pattern .= $data[$key];

            }
        }

        return $pattern;
    }

    private function separateCSVData(int $csv_start_row){

        $row_num = 0;

        foreach($this->_file as $row){

            if($row_num >= $csv_start_row){

                $this->_new_file_data[] = $row;
            }

            else{

                $this->_old_file_data[] = $row;
            }

            $row_num ++;
        }
    }

    private function setOptsFromGET(){

        /* Set default Options */

        $this->_opt['save'] = 0;

        $this->_opt['show_reps'] = 1;

        /* Replace default Options with GET params */

        foreach($_GET as $key=>$value){

            $this->_opt[$key] = $value;
        }  

    }

    private function readCSVfile(){

        $row = 0;

        if (($handle = fopen($_SERVER['DOCUMENT_ROOT']."/data/test.csv", "r")) !== FALSE) {
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {                
                
                $row++;

                for ($i=0; $i < count($data); $i++) {

                    if($row == 1){

                        $this->_fields[$i] = $data[$i];                        
                    } 
                    
                    else{

                        $this->_file[$row-2][$this->_fields[$i]] = $data[$i];
                    }
                    
                }
            }
            fclose($handle);

        } 

    }

    public function getCityData($slug){

        $response = array();

        foreach($this->_file as $row){

            if($row['city'] == $slug){

                $response[] = $row;

            }
        }

        echo json_encode($response);
    } 

    private function printAttentions($pattern, $mode){ 
        
        $count_patterns = $this->getCountPatterns($pattern);
        
        if(!empty($count_patterns)){

            return $this->printAttentionsRow($pattern, $count_patterns, $mode);

        }

        else{

            echo ($mode === 'show')?'<br/><span class="success-info"> No '.$pattern.' pattern repeats found</span><hr>':'';

        }          
               
    }


    private function printAttentionsRow(string $pattern, array $count_patterns, string $show_mode){

        $reps_rows = array(); 


            echo($show_mode === 'show')?'<br><span class="warning-info"><h3>ATTENTION!</h3></span>':'';


            foreach($count_patterns as $row){                

                $row['rows'] = str_replace(",,","",$row['rows']);

                /**
                 * If $mode == show - display ATTENTIONS warning
                 */

                if($show_mode === 'show'){
                    
                    echo '<br><b><span class="warning-info">'.$row['count'].' Repeating pattern " '.$pattern.' " ('.$row['pattern'].') found at CSV rows: '.$row['rows'].' </b>(Rows '.$this->getCSVRows($row['rows']).' in CSV file)</span>';
                    echo '<br><br><span class="danger-info">Must be deleted '.($row['count']-1).' row(s): '.substr($row['rows'],2, strlen($row['rows'])).'</span><br><br>';
                                    
                }                              
                
                if(isset($row['rows']) && !empty($row['rows'])){                
                
                    $reps_rows[] = $row['rows'];

                }
            }
            
            echo($show_mode === 'show')?'<hr>':'';
            
            return $reps_rows;

    }

    private function getCSVRows($data){

       $data = explode(',', $data);

        foreach($data as $key=>$value){
            
            $data[$key] = $value + 2;
        }

        return implode(",", $data);


    }

   
    private function checkPatternExists($comp_arr, $value){

        foreach($comp_arr as $row){

            if($row['pattern'] == $value){               

                return true;
            }
        }

        return false;

    }

    private function getCountPatterns(string $pattern){

        if(!array_key_exists($pattern, $this->_attentions)){            

            return false; 
         } 
        
        
        $source_array = $this->_attentions[$pattern];

        $count_patterns=array();

        $count_patterns_row = 0;

        $hasValue = 0;         
          
        
        if(isset($source_array) && !empty($source_array)){

            foreach($source_array as $key=>$value){          
            
                if(!count($count_patterns)){               
    
                    $count_patterns[$count_patterns_row]['pattern'] = $value;
    
                    $count_patterns[$count_patterns_row]['count'] = "";
    
                    $count_patterns[$count_patterns_row]['rows'] = ",";
    
                    $count_patterns_row ++;
    
                }
                
                foreach($count_patterns as $pat_key=>$pat_value){                
                    
                    if($count_patterns[$pat_key]['pattern'] != $value){                    
    
                        if(!$this->checkPatternExists($count_patterns,$value)){
    
                           
                            $count_patterns[$count_patterns_row]['pattern'] = $value;
    
                            $count_patterns[$count_patterns_row]['count'] = 1;
    
                            $count_patterns[$count_patterns_row]['rows'] = $key;
    
                            $count_patterns_row ++;
    
                        }
                                            
                    } 
    
                    else{                    
    
                        $count_patterns[$pat_key]['count'] ++;
    
                        if(!$count_patterns[$pat_key]['rows']){
    
                            $count_patterns[$pat_key]['rows'] .= ','.$key;
                        }
    
                        else{
    
                            $count_patterns[$pat_key]['rows'] .= ','.$key;
                        }
    
                    }
                }
              
            } 
        }
                
       
        return $count_patterns;
    }

    private function findDataRepeats(array $data, string $pattern){        

        $coinsidence_patterns = array();        

        $row_count = 0;       

        foreach($data as $row){

            $compared_pattern = $this->getComparedPattern($row, $pattern);           
                        
            // if(!$compared_pattern) return false;
            
            

            
            $coinsidence_count = 0;

            foreach ($this->_file as $file_item){              

                $item_address = $this->getComparedPattern($file_item, $pattern);

                if($compared_pattern == $item_address){

                    $coinsidence_count++;

                    if($coinsidence_count == 2){                       
                      
                        $this->_attentions[$pattern][$row_count] = $compared_pattern;       
                         
                        $coinsidence_patterns = $this->optimizeNonUniqueArray($coinsidence_patterns, $compared_pattern);    
                    }
                    
                }                
                
            }

            $row_count++; 
        }
 

        return $coinsidence_patterns;

    }

    private function optimizeNonUniqueArray($coinsidence_patterns, $compared_address){

        $coinsidence_patterns[] = $compared_address;

        $c_count = 0;

        foreach($coinsidence_patterns as $key=>$value){

            if($value == $compared_address){                

                $c_count++;

                if($c_count > 1){

                    unset($coinsidence_patterns[$key]);

                }

            }
        } 

        return $coinsidence_patterns;

    }

    private function deleteNonUniqueData($coinsidence_patterns, $c_pattern){  
        
        $repeats_array = $this->createCoinsPatternsArray($coinsidence_patterns);      
 

        $row_count = 0;

        $rows_deleted = 0;

        $deleted_row = 0;

        $rows_log = "";
      
        foreach ($this->_file as $key=>$value){            

        $compared_row = $this->getComparedPattern($value,$c_pattern); 
            
        if($coinsidence_patterns){
           
            foreach($coinsidence_patterns as $pattern){             
    
                if($compared_row == $pattern){                    

                    $repeats_array[$pattern]++;                 

                    if($repeats_array[$pattern] > 1){                        
                     
                        unset($this->_file[$key]); 
                        
                        $deleted_row =  $row_count;
                       
                        $rows_deleted++;

                        $rows_log .= $row_count.',';
                    }
                    
                }                
                    
            } 

        }
            
            $row_count++;

        } 
       
        return [
            
            'c_pattern'=>$c_pattern,

            'rows_deleted'=> $rows_deleted,

            'row_count'=>$row_count,

            'rows_log' => $rows_log,

            'deleted_row'=>$deleted_row,

            'compared_row'=>$compared_row

        ];         
        
    }

    private function getComparedPattern(array $data, string $pattern){

        $pattern_string = ""; 
        
        $pattern_error = array();
               
        foreach($this->_check_patterns[$pattern] as $check_pattern){            

            /* If Pattern Key dont exist (wrong naming) - return false */

            if(array_key_exists($check_pattern, $data)){

                $pattern_string .= $data[$check_pattern]; 
            
            }

            else{ 
                
                $pattern_error[] = $check_pattern;           
               
            }
            
        }

        if(!empty($pattern_error)){

            echo '<span class="danger-info"><b>Pattern Error. Check $this->_check_patterns for following Pattern Keys: </b></span>';

            foreach($pattern_error as $pattern){

                echo $pattern.' , ';
            }

        }           
        
        return $pattern_string;
        
    }

    private function createCoinsPatternsArray($coinsidence_patterns){

        if($coinsidence_patterns){

            $repeats_array = array();

            foreach($coinsidence_patterns as $pattern){

                $repeats_array[$pattern] = 0;
            }

            return $repeats_array;

        }

    }

    private function rearrangeArray($orig_array){

        $rearranged_array = array();

        foreach($orig_array as $item){

            $rearranged_array[] = $item;
        }

        return $rearranged_array;
    }
}