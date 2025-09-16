<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;

/**
 * A simple controller to test that the app is working.
 * This file will be removed in production.
 */
class PlaygroundController extends Controller
{
  public function index(): View|Application|Factory
  {
    // Enviar arreglos a la vista, pasándolos como parámetros.
    return view('playground.playground');
  }

  public function show()
  {
    //
  }

  public function create()
  {
    //
  }
  public function store()
  {
    //
  }
  public function edit()
  {
    //
  }
  public function update()
  {
    //
  }
  public function destroy()
  {
    //
  }
}
