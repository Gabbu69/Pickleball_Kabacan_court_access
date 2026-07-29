@extends('layouts.public')

@section('title', $post->title.' — Kabacan PicklePlay')
@section('description', $post->excerpt ?: Str::limit(strip_tags($post->body), 155))

@section('content')
    <article class="content-detail">
        <div class="site-container max-w-4xl py-16 sm:py-24">
            <a href="{{ route('content.index') }}" class="back-link">← Back to community updates</a>
            <p class="eyebrow mt-10">{{ ucfirst($post->type) }}{{ $post->court ? ' · '.$post->court->name : '' }}</p>
            <h1>{{ $post->title }}</h1>
            @if ($post->starts_at)<p class="content-date">{{ $post->starts_at->format('M j, Y g:i A') }}</p>@endif
            @if ($post->image_path)<img class="content-image" src="{{ asset($post->image_path) }}" alt="" loading="eager">@endif
            <div class="content-prose">{!! nl2br(e($post->body)) !!}</div>
        </div>
    </article>
@endsection
