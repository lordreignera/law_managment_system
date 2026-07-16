@extends('layouts.admin')

@section('title', 'User Guide')
@section('page-title', 'User Guide')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>System User Guide</h2>
                <span>Quick operating guide for staff using the firm management system.</span>
            </div>
        </div>

        <div class="kfms-guide-grid">
            <a href="#first-steps"><i class="mdi mdi-login"></i><strong>First Steps</strong><span>Login, profile, signatures, and navigation.</span></a>
            <a href="#clients"><i class="mdi mdi-account-multiple"></i><strong>Clients</strong><span>Intake, approval, and client records.</span></a>
            <a href="#matters"><i class="mdi mdi-briefcase"></i><strong>Matters</strong><span>Opening matters, instructions, files, and billing.</span></a>
            <a href="#litigation"><i class="mdi mdi-gavel"></i><strong>Litigation</strong><span>Cause lists, court dates, rulings, and execution.</span></a>
            <a href="#letters"><i class="mdi mdi-file-sign"></i><strong>Letters</strong><span>Templates, previews, signatures, and sharing.</span></a>
            <a href="#messages"><i class="mdi mdi-message-text-outline"></i><strong>Messages</strong><span>Internal and client communication.</span></a>
        </div>
    </section>

    <section class="kfms-panel" id="first-steps">
        <div class="kfms-panel-header">
            <div>
                <h2>First Steps</h2>
                <span>Set up your account before using modules.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Login with the email approved by the administrator.</li>
            <li>Open your profile from the top-right menu and upload your profile photo.</li>
            <li>Upload your signature if you will sign letters, opinions, or other documents.</li>
            <li>Use the left sidebar to open the modules available to your role.</li>
            <li>If a menu is missing, ask the system administrator to review your role permissions.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="clients">
        <div class="kfms-panel-header">
            <div>
                <h2>Client Flow</h2>
                <span>How new clients move into the system.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Create a client intake from Client Management.</li>
            <li>Capture the mandatory fields, preferred advocate, referral details, and conflict parties.</li>
            <li>The intake appears under records awaiting review.</li>
            <li>An authorised user reviews and approves or rejects the intake with a reason.</li>
            <li>Approved intakes become approved client records where more details, engagements, and matters can be added.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="matters">
        <div class="kfms-panel-header">
            <div>
                <h2>Matter Flow</h2>
                <span>From approved client to active file.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Create a matter from the approved client or Matter Management.</li>
            <li>Add practice area, matter category, responsible advocates, date opened, privacy status, and file summary.</li>
            <li>Use the matter workspace to add instructions, documents, billing, costs, or litigation activity.</li>
            <li>Create letters directly from the matter when the file needs correspondence or opinions.</li>
            <li>Close or archive matters only after work, billing, and documents are complete.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="litigation">
        <div class="kfms-panel-header">
            <div>
                <h2>Litigation Flow</h2>
                <span>How court work is tracked.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Open a matter first, then move it to litigation where court work is required.</li>
            <li>Add the cause list or court file with case number, court, judge, event type, and next date.</li>
            <li>Update filing, service, hearings, rulings, judgment, taxation, and execution steps.</li>
            <li>Use the litigation dashboard to monitor active court work and pending lifecycle stages.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="letters">
        <div class="kfms-panel-header">
            <div>
                <h2>Letters & Opinions</h2>
                <span>Creating branded correspondence.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Open Letters & Opinions and choose Create Letter.</li>
            <li>Select a template such as demand notice, legal opinion, engagement letter, or general letter.</li>
            <li>Link the letter to a client, matter, or recovery account where applicable.</li>
            <li>Use the live preview to confirm the logo, reference, recipient, subject, body, and signature.</li>
            <li>Choose a signature option: profile signature, uploaded signature, drawn signature, or unsigned draft.</li>
            <li>Submit the draft for review, approve it, mark it as sent, then upload the received copy when returned.</li>
            <li>Only letters marked visible to client will appear in the client portal.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="messages">
        <div class="kfms-panel-header">
            <div>
                <h2>Messages</h2>
                <span>Internal and client communication.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Use Messages to communicate with yourself, individual users, departments, branches, or the whole firm.</li>
            <li>Client matter messages are visible only to the client and the assigned matter team.</li>
            <li>If Laravel Cloud WebSockets are active, messages appear instantly. Without WebSockets, messages still save but may require page refresh.</li>
            <li>Uploaded chat files are stored using the configured document storage bucket.</li>
        </ol>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Other Modules</h2>
                <span>Quick reminders for operational teams.</span>
            </div>
        </div>
        <div class="kfms-guide-columns">
            <div>
                <h3>Recoveries</h3>
                <p>Managers import recovery portfolios, assign officers, and review daily or weekly recovery reports. Officers update follow-ups and payments from assigned accounts.</p>
            </div>
            <div>
                <h3>Securities</h3>
                <p>Register securities when received, attach documents, track financial institution, branch, MZO, handler, and return details from the action button after registration.</p>
            </div>
            <div>
                <h3>Finance</h3>
                <p>Use Chart of Accounts, expenses, petty cash, ledgers, requisitions, and finance dashboard for controlled finance operations.</p>
            </div>
            <div>
                <h3>Human Resources</h3>
                <p>HR users manage staff records and leave. Staff can use leave self-service where their role permits it.</p>
            </div>
        </div>
    </section>
@endsection
