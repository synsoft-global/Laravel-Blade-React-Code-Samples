/**
 * Description: Entry point for the application.
 *  This component initializes the application and renders the main App component.
 * Version: 1.0.0
 * Author: Synsoft Global
 * Author URI: https://www.synsoftglobal.com/
 */
import React    from 'react';
import ReactDOM from 'react-dom';
import App      from "./Components/App";

let rootEl = document.getElementById('currentNumberEntries');
ReactDOM.render(<App profileId={rootEl.getAttribute('data-profileId')}/>, rootEl);