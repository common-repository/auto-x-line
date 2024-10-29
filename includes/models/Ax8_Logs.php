<?php
namespace Ax8\Models;
use Ax8\Models\Ax8_MainModel;
use Ax8\Ax8_Helper as Helper;
class Ax8_Logs extends Ax8_MainModel {
    /**
     * Name for table without prefix
     *
     * @var string
     */
    protected $table = AUTO_X_LINE_PREFIX.'logs';
    /**
     * Columns that can be edited - IE not primary key or timestamps if being used
     */
    protected $fillable = [
        'account_id',
        'log_time',
        'log_type',
        'message'
    ];
    /**
     * Disable created_at and update_at columns, unless you have those.
     */
    public $timestamps = false;
    /** Everything below this is best done in an abstract class that custom tables extend */
    /**
     * Set primary key as ID, because WordPress
     *
     * @var string
     */
    protected $primaryKey = 'log_id';
    /**
     * Make ID guarded -- without this ID doesn't save.
     *
     * @var string
     */
    protected $guarded = [ 'message','account_id' ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['created_at','updated_at'];
    public function getLogs($where,$request){
        $rows = $this->where($where)->orderBy('log_id', 'DESC')->get()->toArray();
        if(!empty($rows)){
            $data           = $this->getPaginatorItems($rows,$request);
            $data['items']  = $this->formatRows($data['items']);
            $rows           = $this->getPaginator($data,$request);
        }
        return $rows;
    }
    public function formatRows($rows){
        $dateFormat = get_option(AUTO_X_LINE_PREFIX.'date_format');
        if($dateFormat == '' || $dateFormat == false){
            $dateFormat = "WP_FORMAT";
        }
        foreach($rows as $i=>$row){
            $row['log_time'] = Helper::formatDate($row['log_time'],$dateFormat);
            $rows[$i] = $row;
        }
        return $rows;
    }
    /**
     * Overide parent method to make sure prefixing is correct.
     *
     * @return string
     */
    // public function getTable()
    // {
    //     // In this example, it's set, but this is better in an abstract class
    //     if ( isset( $this->table ) ){
    //         $prefix =  $this->getConnection()->db->prefix;
    //         return $prefix . $this->table;
    //     }
    //     return parent::getTable();
    // }
}
