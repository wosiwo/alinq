<?php

/**
 *  A Array Linq for PHP -- plan to realize by using php extension
 *  模仿Alinq,不使用SPL:RecursiveArrayIterator的linq实现
 *  @author onceme 
 */
class Alinq{

    const ALINQ_CLOSURE_RETURN_TYPE_BOOL = 'bool';
    const ALINQ_CLOSURE_RETURN_TYPE_OBJECT = 'object';
    const ALINQ_CLOSURE_RETURN_TYPE_ARRAY = 'array';
    const ALINQ_ORDER_ASC = 'asc';
    const ALINQ_ORDER_DESC = 'desc';
    
    const ALINQ_ORDER_TYPE_NUMERIC = 1;
    const ALINQ_ORDER_TYPE_ALPHANUMERIC = 2;
    const ALINQ_ORDER_TYPE_DATETIME = 3;
    private $dataSource;
    /**
     *  初始化类时指定数组原[后期考虑缓存各个数组源，及其查询结果]
     */
    public function __construct(Array &$dataSource = array())
    {
        $this->dataSource = $dataSource;     
    }

    /**
     * 以传入数组实例化一个新的linq对象
     */
    public static function Instance(Array &$newDataSource = array()){
            return  new self($newDataSource);   //建立一个新的对象,便于于灵活的使用不同数组
    }



    /**
     * 返回符合 $closure(闭包) 条件的第一个结果
     * 
     * @param ObjectClosure $closure    a closure that returns boolean.
     * @return Alinq    The first item from this according $closure
     */
    public function Single($closure)
    {
        $applicables = $this->GetApplicables($closure, 1);
               
        return $applicables->ToArray();
    }  


    /**
     * 根据 $closure(闭包) 生成的key对数组进行分组
     * 
     * @param ObjectClosure $closure    a closure that returns an item as key, item can be any type.
     * @return Alinq
     */
    public function GroupBy($closure){

        foreach($this->dataSource as $key => $value)        
        {
            
            $result = call_user_func_array($closure, array($key, $value));
                
            $groups[$result][$key] = $value;
                
        }
        return self::Instance($groups);         
        /*  返回对象以便使用链表方式
            $p->GroupBy(function($k, $v){ 
                return (date('Y-m',$v['date']->getTimeStamp())); 
            })->Single(function($k, $v){ return $k > '2015-03'; });

         */
    }

    /**
     * 将给定的数组覆盖到数据源数组中
     * 
     * @param Array $array
     * @return Alinq
     */
    public function Concat(Array $array)
    {    
        $data = $this->dataSource;
        foreach ($array as $key => $value) { 
            
            $data[$key] = $value;            
        }
        
        return self::Instance($data);  //以结果集实例化新的对象返回，用于链表操作
    }


   /**
     * Creates a new Alinq object from items which are a form of Array according to $closure 
     * 打散二维数组  array("key"=>array("key_1"=>1,"key_2"=>2))  => array(0=>1,1=>2)
     * @param ObjectClosure $closure    a closure that returns an item that is a form of Array.
     * @return Alinq
     */
    public function SelectMany($closure)
    {
        $applicables = $this->GetApplicables($closure, 0, self::ALINQ_CLOSURE_RETURN_TYPE_OBJECT);
        $applicables = $applicables->ToArray();
        $many = array();
        
        foreach($applicables as $applicable)
        {
            if(!is_array($applicable))
                continue;
            
            foreach($applicable as $applicablePart)
                $many[] = $applicablePart;
        }
        
        return self::Instance($many);
    }

    /**
     * Creates a new Alinq object from items that are determined by $closure 
     * 
     * @param ObjectClosure $closure    a closure that returns an item to append, item can be any type.
     * @return Alinq
     */
    public function Select($closure)
    {
        return $this->GetApplicables($closure, 0, self::ALINQ_CLOSURE_RETURN_TYPE_OBJECT);
    }
     

     /**
     * Alinq::Where() 
     * Filters the Alinq object according to closure return result.
     * 
     * @param ObjectClosure $closure     a closure that returns boolean
     * @return Alinq    Filtered results according to $closure
     */
    public function Where($closure)
    {         
        return $this->GetApplicables($closure);
    }
    
    /**
     * Alinq::Skip()
     * Skips first $count item and returns remaining items
     * 
     * @param int $count    skip count
     * @return Alinq
     */
    public function Skip($count)
    {
        return self::Instance(array_slice($this->dataSource, $count, $this->count()));
    }
    
    /**
     * Alinq::Take()
     * Takes first $count item and returns them
     * 
     * @param int $count    take count
     * @return  Alinq
     */
    public function Take($count)
    {
        return $this->GetApplicables(function($k, $v){ return true; }, $count, self::ALINQ_CLOSURE_RETURN_TYPE_BOOL);
    }
    
    /**
     * Determines if all of the items in this object satisfies $closure
     * 
     * @param ObjectClosure $closure    a closure that returns boolean
     * @return bool
     */
    public function All($closure)
    {
        return ($this->count() == $this->GetApplicables($closure)->count());
    }
    
    /**
     * Determines if any of the items in this object satisfies $closure
     * 
     * @param ObjectClosure
     * @return bool
     */
    public function Any($closure)
    {
        $result = $this->Single($closure);;
        return  !empty($result);
    }
    
    /**
     * Computes the average of items in this object according to $closure
     * 
     * @param ObjectClosure $closure    a closure that returns any numeric type (int, float etc.)
     * @return double   Average of items
     */
    public function Average($closure)
    {
        $resulTotal = 0;
        $averagable = 0;
        
        foreach ($this->dataSource as $key => $value) {
            
            if(!is_numeric(($result = call_user_func_array($closure, array($key, $value)))))
                continue;
            
            $resulTotal += $result;
            $averagable++;            
        }        
        return (($averagable == 0)? 0 : ($resulTotal/$averagable)); 
    }
    
    private function Order($closure, $direction = self::ALINQ_ORDER_ASC)
    {
        $applicables = $this->GetApplicables($closure, 0, self::ALINQ_CLOSURE_RETURN_TYPE_OBJECT);

        $sortType = self::ALINQ_ORDER_TYPE_NUMERIC;
        if(is_a($applicables->ElementAt(0), 'DateTime'))
            $sortType = self::ALINQ_ORDER_TYPE_DATETIME;
        elseif(!is_numeric($applicables->ElementAt(0)))
            $sortType = self::ALINQ_ORDER_TYPE_ALPHANUMERIC;
        
        if($sortType == self::ALINQ_ORDER_TYPE_DATETIME)
        {
            $applicables = $applicables->Select(function($k, $v){ return $v->getTimeStamp(); });
            $sortType = self::ALINQ_ORDER_TYPE_NUMERIC;
        }            
        $applicables = $applicables->ToArray();


        if($direction == self::ALINQ_ORDER_ASC)
            asort($applicables, (($sortType == self::ALINQ_ORDER_TYPE_NUMERIC)? SORT_NUMERIC : SORT_LOCALE_STRING));
        else
            arsort($applicables, (($sortType == self::ALINQ_ORDER_TYPE_NUMERIC)? SORT_NUMERIC : SORT_LOCALE_STRING));

        $ordered = array();
        foreach($applicables as $key => $value)
            $ordered[$key] = $this->dataSource[$key];
            
        return self::Instance($ordered);
    }
    
    /**
     * Orders this objects items in ascending order according to the selected key in closure
     * 
     * @param ObjectClosure $closure    a closure that selects the order key, key can be anything
     * @return Alinq    Ordered items
     */
    public function OrderBy($closure)
    {
        return $this->Order($closure, self::ALINQ_ORDER_ASC);
    }
    
    /**
     * Orders this objects items in descending order according to the selected key in closure
     * 
     * @param ObjectClosure $closure    a closure that selects the order key, key can be anything
     * @return Alinq    Ordered items
     */
    public function OrderByDescending($closure)
    {
        return $this->Order($closure, self::ALINQ_ORDER_DESC);
    }    
    
    /**
     * Gets the maximimum item value according to $closure
     * 
     * @param ObjectClosure $closure    a closure that returns any numeric type (int, float etc.)
     * @return  numeric Maximum item value
     */
    public function Max($closure)
    {
        $max = null;    
        foreach ($this->dataSource as $key => $value) {   
                      
            if(!is_numeric(($result = call_user_func_array($closure, array($key, $value)))))
                continue;
            
            if(is_null($max))
                $max = $result;
            elseif($max < $result)
                $max = $result;                
            
        }
        
        return $max; 
    }   
    
 
     /**
     * Gets the minimum item value according to $closure
     * 
     * @param ObjectClosure $closure    a closure that returns any numeric type (int, float etc.)
     * @return  numeric Minimum item value
     */
    public function Min($closure){
        $min = null;    
        foreach ($this->dataSource as $key => $value) {   
              
            if(!is_numeric(($result = call_user_func_array($closure, array($key, $value)))))
                continue;
            
            if(is_null($min))
                $min = $result;
            elseif($min > $result)
                $min = $result;
        }
        
        return $min; 
    }      

    /**
     *
     *      count
     */
    public function count(){
        return count($this->dataSource);
    }
    
    /**
     * Returns distinct item values of this 
     * 
     * @return Alinq    Distinct item values of this 
     */
    public function Distinct()
    {
        return self::Instance(array_unique($this->dataSource));
    }
    
    /**
     * Intersects an Array with this
     * 
     * @param Array $array  Array to intersect
     * @return Alinq    intersected items
     */
    public function Intersect(Array $array)
    {
        return self::Instance(array_intersect((Array)$this, $array));
    }    
    
    /**
     * Finds different items
     * 
     * @param Array $array
     * @return  Alinq   Returns different items of this and $array
     */
    public function Diff(Array $array)
    {
        return self::Instance(array_diff($this->dataSource, $array));
    }
    
    /**
     * Alinq::ElementAt()
     * 
     * @param int $index
     * @return  array  Item at $index
     */
    public function ElementAt($index)
    {
        $values = array_values($this->dataSource);        
        return $values[$index];
    }

    /**
     * Alinq::First()
     * 
     * @return  array  Item at index 0
     */
    public function First()
    {
        return $this->ElementAt(0);
        // return array_slice($this->dataSource, 0, 1);
    }
    
    /**
     * Alinq::Last()
     * 
     * @return  array  Last item in this
     */
    public function Last()
    {
        return $this->ElementAt((count($this->dataSource)-1));
    }    


    //根据条件筛选数组
    private function GetApplicables($closure, $count = 0, $closureReturnType = self::ALINQ_CLOSURE_RETURN_TYPE_BOOL)
    {
        $applicables = array();
        
        $totalApplicable = 0;
        foreach($this->dataSource as $storedKey => $stored)
        {            
            if($count > 0 && $totalApplicable >= $count)
                break;
            
            switch($closureReturnType)
            {   
                case self::ALINQ_CLOSURE_RETURN_TYPE_BOOL:                    
                    if(!is_bool(($returned = call_user_func_array($closure, array($storedKey, $stored)))) || !$returned)
                        continue;
                        
                    $applicables[$storedKey] = $stored;
                    $totalApplicable++;                                            
                break;
                case self::ALINQ_CLOSURE_RETURN_TYPE_OBJECT:
                    $applicables[$storedKey] = call_user_func_array($closure, array($storedKey, $stored));
                    $totalApplicable++;                        
                break;    
            }
        }          
      
        return self::Instance($applicables);        
    }


    /*
     *  二维数组行列转换
     */
    public function Array2DInverse(){
            $precent = array();
            foreach($this->dataSource as $i=>$arr){
                    if(is_array($arr)){
                        foreach($arr as $j=>$v){
                            $precent[$j][$i]=$array[$i][$j];
                        }
                    }else{
                            $precent[0][$i]=$arr;
                    }
           
        }
        return self::Instance($precent);
    }


    /*
     *  二维数组添加列
     */
    public function ArrayAddColumn($add=array(),$fieldName=""){
        $array = array();
        foreach($this->dataSource as $k=>$row){
                $array[$k][$fieldName]  = isset($add[$k])?$add[$k]:"";          
        }       
        return self::Instance($array);
    }

    /*
     * 批量获取数组中的数据[获取某几列数据]
     */
    public function GetArrayColumns($keys){
        $items = array();
        foreach ($this->dataSource as $row){
                foreach ($row as $field=>$val){
                        if(in_array($field, $keys)){
                                $items[$field][] = $val;
                        }
                }
        }

        return self::Instance($items);
    }

    //获取某一列
    public function GetArrayColumn($key){
        $column = $this->getArrayColumns($this->dataSource, array($key));
        $column = $column->getArrayItem($key,array());
        return $column;
    }

    /**
     * 获取数组中的值
     */
    public function GetArrayItem($key, $default = 0){
        $itemValue = isset($this->dataSource[$key])?$this->dataSource[$key]:$default;
        return $itemValue;
    }


    /**
     * 返回结果集
     * 
     * @return Array    Alinq as Array
     */
    public function ToArray(){
        return $this->dataSource;       //返回结果集
    }








}