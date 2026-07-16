@extends('layouts.client')

@section('title', 'Help Guide')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Client Portal Guide</h2>
                <span>How to follow your matters and communicate with the firm.</span>
            </div>
        </div>

        <div class="kfms-guide-grid">
            <a href="#portal-access"><i class="mdi mdi-account-lock-outline"></i><strong>Access</strong><span>Use your registered client email.</span></a>
            <a href="#matters"><i class="mdi mdi-briefcase-outline"></i><strong>Matters</strong><span>View your own matter information.</span></a>
            <a href="#documents"><i class="mdi mdi-file-document-outline"></i><strong>Documents</strong><span>Download documents shared by the firm.</span></a>
            <a href="#messages"><i class="mdi mdi-message-text-outline"></i><strong>Messages</strong><span>Chat with your assigned advocate.</span></a>
        </div>
    </section>

    <section class="kfms-panel" id="portal-access">
        <div class="kfms-panel-header">
            <div>
                <h2>Portal Access</h2>
                <span>Your account is private to your registered client record.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Use the portal link shared by the firm.</li>
            <li>Create an account only with the email already registered in the firm records.</li>
            <li>If your email is not recognised, contact the firm to update your client details.</li>
            <li>After login, you can only see matters connected to your client record.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="matters">
        <div class="kfms-panel-header">
            <div>
                <h2>My Matters</h2>
                <span>Follow active work shared by the firm.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Open My Matters to view the matters connected to your account.</li>
            <li>Open a matter to see status, assigned advocate, dates, shared documents, and letters.</li>
            <li>Only information marked visible by the firm appears in the client portal.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="documents">
        <div class="kfms-panel-header">
            <div>
                <h2>Documents & Letters</h2>
                <span>Download official documents shared with you.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Open a matter and check Shared Documents.</li>
            <li>Letters and opinions shared by the firm appear under Letters & Opinions.</li>
            <li>Use the download link to save a PDF copy.</li>
        </ol>
    </section>

    <section class="kfms-panel" id="messages">
        <div class="kfms-panel-header">
            <div>
                <h2>Messages</h2>
                <span>Communicate with your assigned advocate.</span>
            </div>
        </div>
        <ol class="kfms-guide-list">
            <li>Open Messages or use the message box on a matter.</li>
            <li>Messages stay linked to the matter so the firm can track the conversation.</li>
            <li>If real-time messaging is enabled, replies appear instantly. Otherwise refresh the page to see new messages.</li>
        </ol>
    </section>
@endsection
