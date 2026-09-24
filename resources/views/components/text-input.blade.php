@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder-slate-500']) !!}>
