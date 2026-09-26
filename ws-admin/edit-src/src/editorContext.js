import { createContext } from 'react';

// What the form's controls may ask of the editor around them: the occurrences
// of a series create their draft folders through the same authenticated save
// the editor uses, and open them in a new tab. Null outside the editor.
export const EditorContext = createContext(null);
