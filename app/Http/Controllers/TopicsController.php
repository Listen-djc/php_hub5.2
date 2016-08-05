<?php

namespace App\Http\Controllers;

use App\Banner;
use App\Category;
use App\Phphub\Core\CreatorListener;
use App\Topic;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Requests\StoreTopicRequest;

class TopicsController extends Controller implements CreatorListener
{
    public function index(Topic $topic)
    {
        $filter = $topic->present()->getTopicFilter();
        $topics = $topic->getTopicsWithFilter($filter, 40);
        $banners = Banner::allByPosition();

        return view('topics.index', compact('topics', 'banners'));
    }

    public function create(Request $request)
    {
        $category = Category::find($request->input('category_id'));
        $categories = Category::all();

        return view('topics.create_edit', compact('category', 'categories'));
    }

    public function store(StoreTopicRequest $request)
    {
        return app('App\Phphub\Creators\TopicCreator')->create($this, $request->except('_token'));
    }

    public function show($id)
    {
        $topic = Topic::findOrFail($id);
        $replies = $topic->getRepliesWithLimit(config('phphub.replies_perpage'));
        $category = $topic->category;
        $categoryTopics = $topic->getSameCategoryTopics();

        $topic->increment('view_count', 1);

        $banners = Banner::allByPosition();
        return view('topics.show', compact('topic', 'replies', 'category', 'categoryTopics', 'banners'));
    }

    public function edit($id)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('update', $topic);

        $categories = Category::all();
        $category = $topic->category;

        if ($topic->body_original) {
            $topic->body = $topic->body_original;
        }

        return view('topics.create_edit', compact('topic', 'categories', 'category'));
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('delete', $topic);

        $topic->delete();

        return redirect(route('topics.index'));
    }

    /**
     * ----------------------------------------
     * User Topic Vote function
     * ----------------------------------------
     */
    public function upvote($id)
    {
        $topic = Topic::find($id);
        app('App\Phphub\Vote\Voter')->topicUpVote($topic);

        return response(['status' => 200]);
    }

    public function downvote($id)
    {
        $topic = Topic::find($id);
        app('App\Phphub\Vote\Voter')->topicDownVote($topic);

        return response(['status' => 200]);
    }
    // 加精
    public function recommend($id)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('recommend', $topic);

        $topic->is_excellent = $topic->is_excellent == 'yes' ? 'no' : 'yes';
        $topic->save();

        return response(['status' => 200, 'message' => lang('Operation succeeded.')]);
    }
    // 置顶
    public function pin($id)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('pin', $topic);
        
        $topic->order = $topic->order > 0 ? 0 : 999;
        $topic->save();

        return response(['status' => 200, 'message' => lang('Operation succeeded.')]);
    }
    // 沉掉主题，就是不再显示了，和置顶不置顶还有区别
    public function sink($id)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('sink', $topic);

        $topic->order = $topic->order >= 0 ? -1 : 0;
        $topic->save();

        return response(['status' => 200, 'message' => lang('Operation succeeded.')]);
    }

    /**
     * ----------------------------------------
     * CreatorListener Delegate
     * ----------------------------------------
     */
    public function creatorFailed($errors)
    {
        return redirect('/');
    }

    public function creatorSucceed($topic)
    {
        return redirect(route('topics.show', array($topic->id)));
    }
}
