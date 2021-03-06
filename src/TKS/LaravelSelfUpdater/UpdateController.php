<?php
/**
 * Copyright (C) 2015 Tobias Knipping
 *
 * based on th Work of Valera Trubachev
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace TKS\LaravelSelfUpdater;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Controller;
use Symfony\Component\Console\Output\StreamOutput;

class UpdateController extends Controller
{
    public function triggerManualUpdate()
    {
        return $this->doUpdate(false);
    }

    public function triggerAutoUpdate()
    {
        if (!Input::isJson()) return '';
        if (Input::json('ref') === 'refs/heads/' . Config::get('self-updater::branch', 'master')) {
            return $this->doUpdate(true);
        }
        return '';
    }

    protected function doUpdate($auto)
    {
        $root = base_path();

        $success = false;
        $migrate_out = $git_pull_out = $git_log_out = $git_commit_hash = '';

        $error = $error_out = '';

        $cancelled = false;
        $responses = Event::fire('self-updater.pre-update', array($auto));
        foreach ($responses as $resp) {
            if ($resp === false) {
                $error = Lang::get('self-updater::messages.error_cancelled');
                $cancelled = true;
                break;
            }
        }

        if (!$cancelled) {
            exec('cd "' . $root . '"; git status --porcelain | grep .;', $status_out, $status_return);

            if ($status_return != 0) {
                exec('cd "' . $root . '"; git pull origin ' . Config::get('self-updater::branch', 'master') . ' 2>&1;', $git_pull_out, $pull_return);
                $git_pull_out = join("\n", $git_pull_out);

                if ($pull_return == 0) {
                    exec('cd "' . $root . '"; git log --oneline ORIG_HEAD..;', $git_log_out);
                    $git_log_out = join("\n", $git_log_out);

                    $git_commit_hash = substr(trim(`git rev-parse HEAD`), 0, Config::get('self-updater::commit_hash_length', 0));

                    Artisan::call('clear-compiled');
                    Artisan::call('dump-autoload');
                    Artisan::call('optimize');

                    try {
                        $migrate_opts = array();
                        if (version_compare(Application::VERSION, '4.2', '>=')) {
                            $migrate_opts['--force'] = true;
                        }

                        $migrate_tmp = fopen('php://memory', 'w+');
                        Artisan::call('migrate', $migrate_opts, new StreamOutput($migrate_tmp));
                        rewind($migrate_tmp);
                        $migrate_out = stream_get_contents($migrate_tmp);

                        $success = true;
                    } catch (\Exception $e) {
                        $error = Lang::get('self-updater::messages.error_migration');
                        $error_out = $e->getMessage();
                    }
                    fclose($migrate_tmp);
                } else {
                    $error = Lang::get('self-updater::messages.error_pull_failed', array('pull_exit_code' => $pull_return));
                }
            } else {
                $error = Lang::get('self-updater::messages.error_dirty_tree');
                $error_out = join("\n", $status_out);
            }
        }

        $site_name = Config::get('self-updater::site_name');
        if (empty($site_name)) $site_name = Input::server('SERVER_NAME');

        $email_data = array(
            'site_name' => $site_name,
            'auto' => $auto,
            'success' => $success,
            'git_commit_hash' => $git_commit_hash,
            'git_pull_out' => $git_pull_out,
            'git_log_out' => $git_log_out,
            'migrate_out' => $migrate_out,
            'error' => $error,
            'error_out' => $error_out,
        );

        $sent_email = $this->sendUpdateEmail($email_data);

        Event::fire('self-updater.post-update', array($success, $error, $auto, $git_commit_hash, $sent_email));

        if (!$auto) {
            return View::make('self-updater::update_email', $email_data);
        }

        return '';
    }

    protected function sendUpdateEmail($email_data)
    {
        if (!Config::get('self-updater::email')) return false;

        $from = Config::get('self-updater::email.from.address', Config::get('mail.from.address'));
        $from_name = Config::get('self-updater::email.from.name', Config::get('mail.from.name'));

        $subject = Config::get('self-updater::email.subject', null);
        $to = Config::get('self-updater::email.to', $from);

        if (empty($to)) return false;

        if (empty($from)) $from = 'self-updater@' . Input::server('SERVER_NAME');
        if (empty($from_name)) $from_name = Lang::get('self-updater::messages.from_name', array('site_name' => $email_data['site_name']));

        $reply_to = Config::get('self-updater::email.reply_to', $from);

        if (is_array($subject)) {
            if (isset($subject[$email_data['success'] ? 'success' : 'error'])) {
                $subject = $subject[$email_data['success'] ? 'success' : 'error'];
            } else {
                $subject = null;
            }
        }

        if (is_callable($subject)) {
            $subject = $subject($email_data['site_name'], $email_data['success'], $email_data['git_commit_hash']);
        }

        if (empty($subject)) {
            if ($email_data['success']) {
                $subject = Lang::get('self-updater::messages.subject_success', array('site_name' => $email_data['site_name'],
                    'commit_hash' => $email_data['git_commit_hash']));
            } else {
                $subject = Lang::get('self-updater::messages.subject_error', array('site_name' => $email_data['site_name']));
            }
        }

        Mail::send('self-updater::update_email', $email_data, function ($message) use ($from, $from_name, $reply_to, $to, $subject) {
            $message->from($from, $from_name)->replyTo($reply_to)->to($to)->subject($subject);
        });

        return true;
    }
}
