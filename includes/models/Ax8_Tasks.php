<?php
namespace Ax8\Models;
use Ax8\Models\Ax8_MainModel;
class Ax8_Tasks extends Ax8_MainModel {
    /**
     * Name for table without prefix
     *
     * @var string
     */
    protected $table = AUTO_X_LINE_PREFIX.'tasks';
    /**
     * Columns that can be edited - IE not primary key or timestamps if being used
     */
    protected $fillable = [
        'thread_no',
        'account_id',
        'post_id',
        'request',
        'response',
        'time',
        'time_gmt',
        'started',
        'completed'
    ];
    /**
     * Disable created_at and update_at columns, unless you have those.
     */
    public $timestamps = true;
    /** Everything below this is best done in an abstract class that custom tables extend */
    /**
     * Set primary key as ID, because WordPress
     *
     * @var string
     */
    protected $primaryKey = 'task_id';
}
