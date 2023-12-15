import React    from 'react';
import ReactDOM from 'react-dom';
import App      from "./Components/App";

let rootEl = document.getElementById('currentNumberEntries');
ReactDOM.render(<App profileId={rootEl.getAttribute('data-profileId')}/>, rootEl);