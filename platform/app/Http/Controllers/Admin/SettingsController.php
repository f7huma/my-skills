<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActionType;
use App\Models\CaseCategory;
use App\Models\DeadlineRule;
use App\Models\Holiday;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\Calendar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request, Calendar $cal)
    {
        $year = (int) $request->query('year', today_local()->year);

        return Inertia::render('Admin/Settings', [
            'tab' => $request->query('tab', 'general'),
            'poaWarningDays' => (int) Setting::get('poa_expiry_warning_days', config('office.poa_expiry_warning_days')),
            'categories' => CaseCategory::orderBy('name')->get(['id', 'name', 'is_active']),
            'actionTypes' => ActionType::orderBy('id')->get(),
            'year' => $year,
            'holidays' => Holiday::whereYear('date', $year)->orderBy('date')->get()->map(fn ($h) => ['id' => $h->id, 'date' => $h->date->toDateString(),
                'hijri' => $cal->hijri($h->date), 'name' => $h->name, 'kind' => $h->kind, 'reviewed' => $h->reviewed]),
            'rules' => DeadlineRule::orderBy('code')->orderByDesc('version')->get()->map(fn ($r) => [
                'id' => $r->id, 'code' => $r->code, 'version' => $r->version, 'name' => $r->name, 'kind' => $r->deadline_kind, 'duration' => $r->duration,
                'dayType' => $r->day_type, 'startEvent' => $r->start_event, 'effectiveFrom' => $r->effective_from->toDateString(),
                'effectiveTo' => $r->effective_to?->toDateString(), 'source' => $r->source_reference, 'status' => $r->status, 'notes' => $r->notes,
                'reviewedAt' => $r->reviewed_at?->toIso8601String()]),
        ]);
    }

    public function general(Request $request)
    {
        $data = $request->validate(['poa_expiry_warning_days' => 'required|integer|min:1|max:365']);
        Setting::put('poa_expiry_warning_days', (int) $data['poa_expiry_warning_days']);
        Audit::log('settings.updated', null, ['poa_expiry_warning_days' => $data['poa_expiry_warning_days']]);

        return back()->with('success', 'حُفظت الإعدادات.');
    }

    public function storeCategory(Request $request)
    {
        CaseCategory::create($request->validate(['name' => 'required|string|max:100|unique:case_categories,name']));

        return back();
    }

    public function updateCategory(Request $request, CaseCategory $category)
    {
        $category->update($request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('case_categories', 'name')->ignore($category->id)], 'is_active' => 'boolean']));

        return back();
    }

    public function saveActionType(Request $request, ?ActionType $actionType = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('action_types', 'name')->ignore($actionType?->id)],
            'resubmission_interval_days' => 'nullable|integer|min:1|max:365', 'interval_day_type' => 'required|in:calendar,business',
            'interval_from_last_same_type' => 'boolean', 'condition_note' => 'nullable|string|max:250', 'is_active' => 'boolean',
        ]);
        $t = $actionType ?? new ActionType;
        $t->fill($data)->save();
        Audit::log('settings.action_type_saved', $t, ['interval' => $t->resubmission_interval_days]);

        return back()->with('success', 'حُفظ نوع الإجراء.');
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate(['date' => 'required|date|unique:holidays,date', 'name' => 'required|string|max:120', 'kind' => 'required|in:official,office']);
        $h = Holiday::create($data + ['reviewed' => true]);
        Audit::log('holiday.created', $h, ['date' => $h->date->toDateString()]);

        return back();
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $holiday->update($request->validate(['name' => 'required|string|max:120', 'reviewed' => 'boolean']));
        Audit::log('holiday.updated', $holiday);

        return back();
    }

    public function destroyHoliday(Holiday $holiday)
    {
        Audit::log('holiday.deleted', $holiday, ['date' => $holiday->date->toDateString()]);
        $holiday->delete();

        return back();
    }

    /** إصدار جديد من القاعدة — لا يُعدَّل الإصدار المعتمد في مكانه */
    public function storeRule(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:60|alpha_dash', 'name' => 'required|string|max:150', 'deadline_kind' => 'required|string|max:40',
            'duration' => 'nullable|integer|min:0|max:3650', 'day_type' => 'required|in:calendar,business', 'start_event' => 'nullable|string|max:40',
            'effective_from' => 'required|date', 'effective_to' => 'nullable|date|after:effective_from', 'source_reference' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);
        $version = (int) DeadlineRule::where('code', $data['code'])->max('version') + 1;
        $r = DeadlineRule::create($data + ['version' => $version, 'status' => 'draft']);
        Audit::log('deadline_rule.created', $r, ['code' => $r->code, 'version' => $version]);

        return back()->with('success', 'أُنشئ الإصدار '.$version.' كمسودة. راجعه ثم اعتمده.');
    }

    public function ruleStatus(Request $request, DeadlineRule $rule)
    {
        $data = $request->validate(['status' => 'required|in:active,retired,draft', 'confirm_review' => 'required_if:status,active|accepted_if:status,active']);
        $rule->forceFill(['status' => $data['status']] + ($data['status'] === 'active' ? ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()] : []))->save();
        Audit::log('deadline_rule.status', $rule, ['status' => $rule->status, 'code' => $rule->code, 'version' => $rule->version]);

        return back()->with('success', 'حُدّثت حالة القاعدة.');
    }
}
