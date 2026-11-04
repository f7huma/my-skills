<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\Task;
use App\Services\Workflow\TaskService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $tasks = Task::with(['legalCase:id,reference,title', 'executionRequest:id,reference'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByRaw('due_on asc nulls last')->limit(500)->get();

        return Inertia::render('Office/Tasks/Index', [
            'tasks' => $tasks->map(fn ($t) => CaseController::task($t) + [
                'case' => $t->legalCase ? ['id' => $t->legalCase->id, 'reference' => $t->legalCase->reference, 'title' => $t->legalCase->title] : null,
                'execution' => $t->executionRequest ? ['id' => $t->executionRequest->id, 'reference' => $t->executionRequest->reference] : null,
            ]),
            'status' => $status,
            'kinds' => Office::options(Task::KINDS),
            'dueModes' => Office::options(collect(Task::DUE_MODES)->except('next_hearing')->all()),
        ]);
    }

    public function store(Request $request, TaskService $svc)
    {
        $svc->create($request->validate((new ProceedingsController)->taskRules()));

        return back()->with('success', 'أُضيفت المهمة.');
    }
}
