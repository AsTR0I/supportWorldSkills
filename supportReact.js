React:

// 1 - библиотеки: react-router-dom

// Создаём контекст:
AuthContext.js:
	import React,{createContext, useState} from "react";
	export const Context = createContext();
	export const AuthContext = ({children}) => {

	    const [isAuth, setIsAuth] = useState(localStorage.getItem('token') || false);

	    const userAuth = () => {
        	setIsAuth(true)
    	}
    	const userLogout = () => {
        	setIsAuth(false)
        	localStorage.removeItem('token')
    	}
    	return(
        	<Context.Provider value={{isAuth,userAuth,userLogout}}>
            	{children}
        	</Context.Provider>
    	)
}

// в index.js:
	import React from 'react';
	import ReactDOM from 'react-dom';
	import {BrowserRouter} from 'react-router-dom';

	import './index.css';
	import App from './App';

	import {AuthContext} from './AuthContext'

	ReactDOM.render(
	<React.StrictMode>

	<AuthContext>
		<BrowserRouter>
			<App />
		</BrowserRouter>
	</AuthContext>
		
	</React.StrictMode>,
	document.getElementById('root')
	);

// PrivateRoute.js:
	import React, { useContext } from "react";
	import { Navigate } from "react-router-dom";
	import { Context } from "./AuthContext";

	const PrivateRoute = ({ children }) => {
	const { isAuth } = useContext(Context);

	if (!isAuth) {
		return <Navigate to="/login" replace />;
	}

	return <>{children}</>;
	};

	export { PrivateRoute };

// App.js:
	import { Routes, Route } from 'react-router-dom';
	import {PrivateRoute} from './PrivateRoute'

function App() {
  return (
	<Routes>
          <Route path='/' index element={
            <PrivateRoute>
              <Home />
            </PrivateRoute>
          } />
	</Routes>
  )}

//   -------------------------------------------------
// скачать файл с сервера:
const fileDownload = async(id) => {
	try {
		const response = await fetch(`http://localhost:8000/api/files/${id}`, {
			method: 'GET',
			headers: {
				Authorization: `Bearer ${localStorage.getItem('token')}`
			}
		});

		if (!response.ok) {
			throw new Error('Failed to download file');
		}

		const blob = await response.blob();
		console.log(response)
		const url = window.URL.createObjectURL(blob);
		const a_link = document.createElement('a');
		a_link.href = url;
		a_link.setAttribute('download' ,'')
		document.body.appendChild(a_link)
		a_link.click();

		a_link.remove()
		window.URL.revokeObjectURL(url)

	} catch (error) {
		console.error('Error downloading file:', error);
	}
}
// Загрузить фаилы:
const [fileForUpload, setFileForUpload] = useState([]);
    const filesResponseHandler = (e) => {
        let filesArr = e.target.files;
        let tempFiles = [];
        for (let i = 0; i < filesArr.length; i++) {
            tempFiles.push(filesArr[i]);
        }
        setFileForUpload([...fileForUpload, ...tempFiles]);
    };

    const submitHandler = async (e) => {
        e.preventDefault();
        const files = new FormData();
        if (fileForUpload.length > 0) {
            for (let i = 0; i < fileForUpload.length; i++) {
                files.append("files[]", fileForUpload[i]);
            }
            try {
                const response = await fetch('http://localhost:8000/api/files', {
                    method: 'POST',
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('token')}`
                    },
                    body: files
                });

                const data = await response.json();
                setFileResponse((prevFileResponse) => [...prevFileResponse, ...data.Body]);
                console.log(fileResponse);
            } catch (error) {
                console.log(error);
            }
        }
    };