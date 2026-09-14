<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $meetings = Meeting::query()
            ->with(['user', 'reviewer'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('reviewer_id', $user->id)))
            ->when($this->search, fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($this->search).'%']))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('held_at')->paginate(15);

        return view('livewire.meetings.index', compact('meetings'));
    }
}
