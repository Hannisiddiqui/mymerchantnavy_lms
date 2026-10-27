<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof ActivityLog) {
            return;
        }

        $type = class_basename($model);
        $desc = "{$type} created";
        $event = strtolower("{$type}.created");

        if ($model instanceof Admin) {
            $event = 'admin.created';
            $desc = "Admin account created for {$model->email} ({$model->name}) with role '{$model->role?->value}'.";
        } elseif ($model instanceof User) {
            $event = 'student.created';
            $desc = "Student account created: {$model->email}".($model->name ? " ({$model->name})" : '').'.';
        } elseif ($model instanceof Course) {
            $event = 'course.created';
            $desc = "Course '{$model->title}' created.";
        } elseif ($model instanceof Test) {
            $event = 'test.created';
            $desc = "Test '{$model->title}' created.";
        } elseif ($model instanceof Question) {
            $event = 'question.created';
            $desc = "Question #{$model->id} created in question bank.";
        }

        ActivityLog::record(
            event: $event,
            description: $desc,
            subject: $model,
            properties: ['attributes' => $model->only(['title', 'name', 'email', 'role', 'id'])]
        );
    }

    public function updated(Model $model): void
    {
        if ($model instanceof ActivityLog) {
            return;
        }

        $dirty = $model->getDirty();
        unset($dirty['updated_at'], $dirty['remember_token']);

        if (empty($dirty)) {
            return;
        }

        $type = class_basename($model);
        $event = strtolower("{$type}.updated");
        $desc = "{$type} updated";

        if ($model instanceof Admin) {
            if (isset($dirty['role'])) {
                $oldRole = $model->getOriginal('role')?->value ?? (string) $model->getOriginal('role');
                $newRole = $model->role?->value ?? (string) $model->role;
                $event = 'admin.role_changed';
                $desc = "Role of admin {$model->email} changed from '{$oldRole}' to '{$newRole}'.";
            } else {
                $event = 'admin.updated';
                $desc = "Admin {$model->email} profile was modified.";
            }
        } elseif ($model instanceof User) {
            if (isset($dirty['is_active'])) {
                $event = $model->is_active ? 'student.activated' : 'student.deactivated';
                $desc = "Student {$model->email} was marked ".($model->is_active ? 'active' : 'inactive').'.';
            } else {
                $event = 'student.updated';
                $desc = "Student {$model->email} profile was modified.";
            }
        } elseif ($model instanceof Course) {
            $event = 'course.updated';
            $desc = "Course '{$model->title}' was modified.";
        } elseif ($model instanceof Test) {
            $event = 'test.updated';
            $desc = "Test '{$model->title}' was modified.";
        } elseif ($model instanceof Question) {
            $event = 'question.updated';
            $desc = "Question #{$model->id} was modified.";
        }

        ActivityLog::record(
            event: $event,
            description: $desc,
            subject: $model,
            properties: [
                'changes' => array_keys($dirty),
                'dirty' => $dirty,
            ]
        );
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof ActivityLog) {
            return;
        }

        $type = class_basename($model);
        $event = strtolower("{$type}.deleted");
        $desc = "{$type} deleted";

        if ($model instanceof Admin) {
            $event = 'admin.deleted';
            $desc = "Admin {$model->email} ({$model->name}) was removed.";
        } elseif ($model instanceof User) {
            // Already explicitly logged with custom reason in StudentAccountDeletion
            return;
        } elseif ($model instanceof Course) {
            $event = 'course.deleted';
            $desc = "Course '{$model->title}' was deleted.";
        } elseif ($model instanceof Test) {
            $event = 'test.deleted';
            $desc = "Test '{$model->title}' was deleted.";
        } elseif ($model instanceof Question) {
            $event = 'question.deleted';
            $desc = "Question #{$model->id} was deleted.";
        }

        ActivityLog::record(
            event: $event,
            description: $desc,
            subject: null,
            properties: ['attributes' => $model->only(['title', 'name', 'email', 'id'])]
        );
    }
}
