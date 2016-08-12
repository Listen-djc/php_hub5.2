<?php

namespace App\Http\Controllers;

use App\Append;
use App\Banner;
use App\Category;
use App\Notification;
use App\Phphub\Core\CreatorListener;
use App\Phphub\Markdown\Markdown;
use App\Phphub\Notification\Notifier;
use App\Topic;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Requests\StoreTopicRequest;

use Auth;

class TopicsController extends Controller implements CreatorListener
{
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['index', 'show']]);
    }

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

    public function update(StoreTopicRequest $request, $id)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('update', $topic);

        $data = $request->only('title', 'body', 'category_id');

        $markdown = new Markdown;
        $data['body_original'] = $data['body'];
        $data['body'] = $markdown->convertMarkdownToHtml($data['body']);
        $data['excerpt'] = Topic::makeExcerpt($data['body']);

        $topic->update($data);

        return redirect(route('topics.show', $topic->id));
    }

    public function destroy($id)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('delete', $topic);

        $topic->delete();

        return redirect(route('topics.index'));
    }

    public function append($id, Request $request)
    {
        $topic = Topic::findOrFail($id);
        $this->authorize('append', $topic);

        $markdown = new Markdown;
        $content = $markdown->convertMarkdownToHtml($request->input('content'));

        $append = Append::create(['topic_id' => $topic->id, 'content' => $content]);

        // 生成通知
        app(Notifier::class)->newAppendNotify(Auth::user(), $topic, $append);

        return response([
            'status' => 200,
            'message' => lang('Operation succeeded.'),
            'append' => $append,
        ]);
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

        // 发送提醒
        Notification::notify('topic_mark_excellent', Auth::user(), $topic->user, $topic);

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
