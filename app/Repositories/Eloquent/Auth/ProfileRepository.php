<?php

namespace App\Repositories\Eloquent\Auth;

use App\Models\Events;
use App\Models\Comments;
use App\Models\Likes;
use App\Models\Wishlist;
use App\Repositories\Contracts\Auth\ProfileRepositoryInterface as AuthProfileRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ProfileRepository implements AuthProfileRepositoryInterface
{

    public function getProfileActivity(
        int $userId,
        int $page = 1,
        int $perPage = 20
    ) {

        return Cache::tags([
            'profile',
            "user:$userId"
        ])
        ->remember(
            "profile_activity_{$userId}_page_{$page}",
            now()->addMinutes(30),
            function () use ($userId,$page,$perPage){

                $activities = collect();


                // Events
                $events = Events::where('user_id',$userId)
                    ->latest()
                    ->get()
                    ->map(function($event){

                        return [
                            'type'=>'event_created',
                            'title'=>'Created event',
                            'data'=>[
                                'id'=>$event->id,
                                'title'=>$event->title,
                            ],
                            'created_at'=>$event->created_at
                        ];

                    });


                $activities = $activities->merge($events);



                // Likes
                $likes = Likes::with('event')
                    ->where('user_id',$userId)
                    ->latest()
                    ->get()
                    ->map(function($like){

                        return [
                            'type'=>'event_liked',
                            'title'=>'Liked event',
                            'data'=>[
                                'event_id'=>$like->event_id,
                                'title'=>$like->event?->title
                            ],
                            'created_at'=>$like->created_at
                        ];

                    });


                $activities=$activities->merge($likes);



                // Comments
                $comments = Comments::with('event')
                    ->where('user_id',$userId)
                    ->latest()
                    ->get()
                    ->map(function($comment){

                        return [
                            'type'=>'comment_created',
                            'title'=>'Commented on event',
                            'data'=>[
                                'event_id'=>$comment->event_id,
                                'comment'=>$comment->comment
                            ],
                            'created_at'=>$comment->created_at
                        ];

                    });


                $activities=$activities->merge($comments);



                // Wishlist
                $wishlist = Wishlist::with('events')
                    ->where('user_id',$userId)
                    ->latest()
                    ->get()
                    ->map(function($item){

                        return [
                            'type'=>'wishlist_added',
                            'title'=>'Added to wishlist',
                            'data'=>[
                                'event_id'=>$item->event_id,
                                'title'=>$item->events?->title
                            ],
                            'created_at'=>$item->created_at
                        ];

                    });


                $activities=$activities->merge($wishlist);



                return $activities
                    ->sortByDesc('created_at')
                    ->values()
                    ->forPage($page,$perPage);

            }
        );

    }



    public function clearUserProfileCache(int $userId): void
    {
        Cache::tags([
            'profile',
            "user:$userId"
        ])->flush();
    }

}
