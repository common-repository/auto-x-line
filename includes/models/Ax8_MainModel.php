<?php
namespace Ax8\Models;
use WeDevs\ORM\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
class Ax8_MainModel extends Model {
    public function getTable()
    {
        // In this example, it's set, but this is better in an abstract class
        if ( isset( $this->table ) ){
            $prefix =  $this->getConnection()->db->prefix;
            return $prefix . $this->table;
        }
        return parent::getTable();
    }
    protected function getPaginator($params,$request){
        extract($params);
        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->from_url(site_url()),
            'query' => $request->get_query_params()
        ]);
    }
    protected function getPaginatorItems($items,$request)
    {
        //$request = new \WP_REST_Request();
        $total = count($items); // total count of the set, this is necessary so the paginator will know the total pages to display
        $page = $request->get_param('page') ?? 1; // get current page from the request, first page is null
        $perPage = 100; // how many items you want to display per page?
        $offset = ($page - 1) * $perPage; // get the offset, how many items need to be "skipped" on this page
        $items = array_slice($items, $offset, $perPage); // the array that we actually pass to the paginator is sliced
       return ['items'=>$items,'total'=>$total,'perPage'=>$perPage,'page'=>$page];
    }
}
