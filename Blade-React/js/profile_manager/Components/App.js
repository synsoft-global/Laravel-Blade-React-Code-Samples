/**
 * Description: This component handles the retrieval of overwritten parameters using an API request
 * and renders the 'Overwrite' component to allow users to interact with and update the 'LTV' parameter.
 * Version: 1.0.0
 * Author: Synsoft Global
 * Author URI: https://www.synsoftglobal.com/
 */
import React, {useEffect, useState}   from 'react';
import ApiClient, {cancelTokenSource} from "../../../../../../../utilities/ApiClient";
import swal                           from "sweetalert";
import {BeatLoader}                   from "react-spinners";
import Box                            from "@material-ui/core/Box";
import {Overwrite}                    from "./Overwrite";

const App = ({profileId}) => {
    // State to manage loading state during API requests
    const [loading, setLoading] = useState(true);  
     // State to store the value of 'LTV' parameter
    const [ltv, setLtv] = useState(null);  

    // useEffect to fetch data when the component mounts
    useEffect(() => {
        setLoading(true);      
        let cancelSource = cancelTokenSource();
        ApiClient.get('profile/get-overwritten-params', {
            params     : {userId: profileId},
            cancelToken: cancelSource.token
        }).then(({data}) => {
            if (data && data.status) { 
                setLtv(data.ltv);          
            }
        }).catch((error) => {
            swal("Oops!", "Unable to determine Overwritten Params!", "error");
        }).then(() => {
            setLoading(false);
        });
        
        // Clean up by cancelling the ajax
        return function cleanup() {cancelSource.cancel();};
    }, []);
    
    return <>
        <h3>QA Tools :</h3>
        
        <Box my={5} width={0.5}>
            {
                loading ? <BeatLoader size={25} color={`#5267C9`} loading={true}/> :
                <>
                    <Overwrite profileId={profileId} value={ltv} setValue={setLtv}
                               overwriteEndpoint={`profile/overwrite-ltv`} identifier={`ltv`} title={`LTV`}
                               inputType={`number`} inputProps={{step: 0.05, min: 0, max: 100}}/>                   
                </>
            }
        </Box>
    </>
};

export default App;
