import React, {useState}              from 'react';
import ApiClient, {cancelTokenSource} from "../../../../../../../utilities/ApiClient";
import swal                           from "sweetalert";
import {BeatLoader}                   from "react-spinners";
import TextField                      from "@material-ui/core/TextField";
import Button                         from "@material-ui/core/Button";
import Typography                     from "@material-ui/core/Typography";
import Box                            from "@material-ui/core/Box";
import DeleteIcon                     from "@material-ui/icons/Delete";

export const Overwrite = ({profileId, value, identifier, setValue, overwriteEndpoint, title, inputType, inputProps, inputLabelProps}) => {
    // State to manage loading state during API requests
    const [loading, setLoading]   = useState(false);
    // State to manage the input value for overwriting
    const [valueInput, setValueInput] = useState(value ?? '');
    
     // Function to handle overwriting values
    const updateValue = (value) => {
        setLoading(true);
        
        let cancelSource = cancelTokenSource();
        ApiClient.post(overwriteEndpoint,
                       {userId: profileId, value: value},
                       {cancelToken: cancelSource.token}
        ).then(({data}) => {
            if (data && data.status) {
                setValue(data.value);
                swal("Success!", `${title} Overwritten!`, "success");
            }
        }).catch((error) => {
            swal("Oops!", error.response.data && error.response.data.message ? error.response.data.message : `Unable to overwrite ${title}!`, "error");
        }).then(() => {
            setLoading(false);
        });
        
        // Clean up by cancelling the ajax
        return function cleanup() {cancelSource.cancel();};
    }
    
     // Function to remove overwrites
    const removeOverwrite = () => {
        setLoading(true);
        
        let cancelSource = cancelTokenSource();
        ApiClient.delete('profile/remove-overwrite',
                         {
                             params     : {userId: profileId, attribute: identifier},
                             cancelToken: cancelSource.token
                         }
        ).then(({data}) => {
            if (data && data.status) {
                setValue(null);
                setValueInput('');
                swal("Success!", `${title} Overwrite Removed!`, "success");
            }
        }).catch((error) => {
            swal("Oops!", error.response.data && error.response.data.message ? error.response.data.message : `Unable to remove ${title} overwrite !`, "error");
        }).then(() => {
            setLoading(false);
        });
        
        // Clean up by cancelling the ajax
        return function cleanup() {cancelSource.cancel();};
    }
    
    return <>
        <h4>{title}</h4>
        {
            loading ? <BeatLoader size={15} color={`#5267C9`} loading={true}/> :
            <>
                <Typography
                    component="h2">{title}: {value !== null ? `${value} (Overwritten)` : `Not Overwritten!`}</Typography>
                <Box display="flex" alignItems="center" justifyContent="left" py={2}>
                    <Box mr={3} minWidth={250}>
                        <TextField fullWidth={true} label={title} variant="filled" type={inputType}
                                   value={valueInput} onChange={(e) => setValueInput(e.target.value)}
                                   inputProps={inputProps} InputLabelProps={inputLabelProps}/>
                    </Box>
                    <Box mx={1}>
                        <Button variant="contained" color="primary"
                                onClick={() => {updateValue(valueInput)}}>Overwrite</Button>
                    </Box>
                    
                    {value !== null &&
                     <Box mx={1}>
                         <Button variant="contained" color="secondary" startIcon={<DeleteIcon/>}
                                 onClick={removeOverwrite}>Overwrite</Button>
                     </Box>
                    }
                </Box>
            </>
        }
    </>
}