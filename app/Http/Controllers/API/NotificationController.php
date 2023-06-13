<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Notification lists api
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request){
        $this->validate($request,[
            'page'   =>'nullable',
            'perPage'=>'nullable',
        ]);
        $query        = SystemNotification::query();
        $notification = $query->where('receiver_id',auth()->user()->id)->orderBy('created_at','DESC');
        //$totalCount   = $query->count();
        $unreadCount  = (clone $query)->where('read_at',null)->count();
        /*For pagination and sorting filter*/
        $result = filterSortPagination($notification);
        $notifications = $result['query']->get();
        $totalCount    = $result['count'];
        return ok(__('Notification list'),[
            'notifications' => $notifications,
            'totalCount'    => $totalCount,
            'unreadCount'   => $unreadCount
        ]);
    }

    /**
     * Notification read api
     *
     * @param  mixed $request, $id
     * @return void
     */
    public function read(Request $request,$id=null){

        $query = SystemNotification::query();

        if($id){
            /*one by one notification read */
            $notification = (clone $query)->where('id', $id)->where('receiver_id', auth()->user()->id)->where('read_at', null)->first();
            if(!$notification){
                return error(__('Allready notification read'),[],'validation');
            }
            //update read timestamp
            $notification->update(['read_at' => now()]);
            return ok(__('Notification read successfully'));
        }else{
            /* All notification read on single click */
            $exists = (clone $query)->where('receiver_id', auth()->user()->id)->where('read_at', null)->exists();
            if(!$exists){
                return error(__('No new notification available'),[],'validation');
            }
            //update read timestamp
            (clone $query)->where('receiver_id', auth()->user()->id)->update(['read_at' => now()]);
            return ok(__('All notifications read successfully'));
        }
    }

    /**
     * Clear all notification
     *
     *
     * @return void
     */
    public function clear(){
        $query  = SystemNotification::query();
        $exists = (clone $query)->where('receiver_id', auth()->user()->id)->exists();
        if(!$exists){
            return error(__('Allready notifications cleared'),[],'validation');
        }
        (clone $query)->where('receiver_id', auth()->user()->id)->delete();
        return ok(__('Notification clear successfully'));
    }

    /**
     * Unread count
     *
     *
     * @return void
     */
    public function unreadCount(){
        $count = SystemNotification::where('receiver_id', auth()->user()->id)->where('read_at', null)->count();
        return ok(__('Unread notification count'), ['count' => $count]);
    }

}
