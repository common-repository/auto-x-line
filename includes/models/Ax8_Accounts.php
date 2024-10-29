<?php
namespace Ax8\Models;
use Ax8\Models\Ax8_MainModel;
class Ax8_Accounts extends Ax8_MainModel {
    /**
     * Name for table without prefix
     *
     * @var string
     */
    protected $table = AUTO_X_LINE_PREFIX.'accounts';
    /**
     * Columns that can be edited - IE not primary key or timestamps if being used
     */
    protected $fillable = [
        'user_id',
        'account_type',
        'access_token',
        'where_to_post',
        'message_format',
        'has_filter',
        'filters'
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
    protected $primaryKey = 'account_id';
    /**
     * Make ID guarded -- without this ID doesn't save.
     *
     * @var string
     */
    protected $guarded = [ 'access_token' ];
}
