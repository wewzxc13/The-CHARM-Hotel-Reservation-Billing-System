'use client';
import React, { useEffect, useState } from 'react';
import { Container, Row, Col, Card, Navbar, Nav, Spinner, Alert, Button, Modal, Form, Toast } from 'react-bootstrap';
import axios from 'axios';
import { FaTachometerAlt, FaClipboardList, FaBed,  FaExclamationTriangle, FaCheckCircle, FaCog, FaEdit } from 'react-icons/fa';
import 'bootstrap/dist/css/bootstrap.min.css';

const StaffSettings = () => {
  const [user, setUser] = useState(null);
  const [formData, setFormData] = useState({
    UserFirstName: '',
    UserLastName: '',
    UserEmail: '',
    UserName: '',
    UserAddress: '',
  });
  const [editFormData, setEditFormData] = useState({
    UserFirstName: '',
    UserLastName: '',
    UserEmail: '',
    UserName: '',
    UserPass: '',
    UserAddress: '',
    OldPassword: '',
    NewPassword: ''
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showEditModal, setShowEditModal] = useState(false); // Modal state

  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const clearMessageAfterTimeout = () => {
    setTimeout(() => {
        setError('');
        setSuccess('');
    }, 3000); // Clear messages after 5 seconds
};

  useEffect(() => {
    const userData = JSON.parse(localStorage.getItem('user'));
    if (userData) {
      console.log("Fetched User Data:", userData); // Log user data

      fetchUserData(userData.UserID);
      setUser(userData);
    } else {
      window.location.href = '/'; // Redirect to login if no user data
    }
  }, []);

  const fetchUserData = async (userId) => {
    try {
      const response = await axios.get('http://localhost/Hotel-API/staff/staff.php', {
        params: {
          operation: 'getUserData',
          userId: userId
        }
      });

      if (response.data) {
        setFormData({
          UserFirstName: response.data.UserFirstName || '',
          UserLastName: response.data.UserLastName || '',
          UserEmail: response.data.UserEmail || '',
          UserName: response.data.UserName || '',
          UserAddress: response.data.UserAddress || '',
        });
      } else {
        setError('Error fetching user data');
      }
    } catch (error) {
      console.error('Error fetching user data:', error);
      setError('Error fetching user data. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleEditClick = () => {
    setEditFormData({
      ...formData,
      OldPassword: '',
      NewPassword: '',
      user_id: user.UserID // Include the user ID here
    });
    setShowEditModal(true);
  };

  const handleEditChange = (e) => {
    const { name, value } = e.target;
    setEditFormData((prevState) => ({
      ...prevState,
      [name]: value,
    }));
  };

  const handleEditSubmit = async () => {
    try {
      const payload = {
        first_name: editFormData.UserFirstName,
        last_name: editFormData.UserLastName,
        email: editFormData.UserEmail,
        username: editFormData.UserName,
        address: editFormData.UserAddress,
        old_password: editFormData.OldPassword,
        new_password: editFormData.NewPassword,
        user_id: user.UserID
      };
      const response = await axios.post('http://localhost/Hotel-API/staff/edit.php', payload);

      if (response.data.success) {
        setSuccess('User information updated successfully!');
        clearMessageAfterTimeout();
       
        setShowEditModal(false); // Close modal

        // Update the user state and localStorage with the new data
        const updatedUser = {
          ...user,
          UserFirstName: editFormData.UserFirstName,
          UserLastName: editFormData.UserLastName
        };
        setUser(updatedUser); // Update state
        localStorage.setItem('user', JSON.stringify(updatedUser)); // Update localStorage

        fetchUserData(user.UserID); // Refresh user data
      } else {
        setError(response.data.message || 'Error updating user information');
        clearMessageAfterTimeout();
      }
    } catch (error) {
      console.error('Error updating user information:', error);
      setError('Error updating user information');
      clearMessageAfterTimeout();
    }
  };

  if (loading) {
    return (
      <div className="text-center">
        <Spinner animation="border" role="status">
          <span className="visually-hidden">Loading...</span>
        </Spinner>
      </div>
    );
  }

  if (!user) {
    return <div>No user found.</div>;
  }

  return (
    <div className="guest-settings">
    <Navbar bg="dark" variant="dark" className="mb-4">
  <Container className="justify-content-center"> {/* Center content within the Container */}
    <Navbar.Brand className="text-center">CHARM Hotel Reservation</Navbar.Brand>
  </Container>
</Navbar>


      <Container fluid>
        <Row>
          <Col lg={3} className="left-side">
            <div className="welcome-section">
              {/* Updated to reflect new user details after editing */}
              <h2>Welcome, {user.UserFirstName} {user.UserLastName}!</h2>
              <Nav className="nav-links">
              <Nav.Link href="/staff/dashboard"><FaTachometerAlt /> Home Page</Nav.Link>
                <Nav.Link href="/staff/reservation"><FaClipboardList /> Reservation</Nav.Link>
                <Nav.Link href="/staff/rooms"><FaBed /> All Rooms</Nav.Link>  
                <Nav.Link className='active-link' href="/staff/settings"><FaCog /> Settings</Nav.Link>
              </Nav>
            </div>
          </Col>

          <Col lg={9} className="right-side d-flex justify-content-center">
            <Card className="mb-4 account-card" style={{ width: '100%' }}>
              <Card.Body>
                <h3 className="text-center">View Account</h3>
                {error && <Alert variant="danger">{error}</Alert>}
                <div className="text-start account-details">
                  <div className="account-item">
                    <h5>First Name:</h5>
                    <p>{formData.UserFirstName}</p>
                  </div>
                  <div className="account-item">
                    <h5>Last Name:</h5>
                    <p>{formData.UserLastName}</p>
                  </div>
                  <div className="account-item">
                    <h5>Email:</h5>
                    <p>{formData.UserEmail}</p>
                  </div>
                  <div className="account-item">
                    <h5>Username:</h5>
                    <p>{formData.UserName}</p>
                  </div>
                  <div className="account-item">
                    <h5>Address:</h5>
                    <p>{formData.UserAddress}</p>
                  </div>
                </div>
              </Card.Body>
              <br/>
   {/* Display error message */}
            {error && (
                        <div className="error-message">
                            <FaExclamationTriangle className="icon" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Display success message */}
                    {success && (
                        <div className="success-message">
                            <FaCheckCircle className="icon" />
                            <span>{success}</span>
                        </div>
                    )}

<br/>
              <Button onClick={handleEditClick}>
                <FaEdit /> Edit Account
              </Button>
            </Card>
          </Col>
        </Row>
      

      </Container>

      {/* Edit Modal */}
      <Modal show={showEditModal} onHide={() => setShowEditModal(false)}>
        <Modal.Header>
          <Modal.Title>Edit Account</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <Form>
            <Form.Group controlId="editFirstName">
              <Form.Label>First Name</Form.Label>
              <Form.Control
                type="text"
                name="UserFirstName"
                value={editFormData.UserFirstName}
                onChange={handleEditChange}
              />
            </Form.Group>
            <Form.Group controlId="editLastName">
              <Form.Label>Last Name</Form.Label>
              <Form.Control
                type="text"
                name="UserLastName"
                value={editFormData.UserLastName}
                onChange={handleEditChange}
              />
            </Form.Group>
            <Form.Group controlId="editEmail">
              <Form.Label>Email</Form.Label>
              <Form.Control
                type="email"
                name="UserEmail"
                value={editFormData.UserEmail}
                onChange={handleEditChange}
              />
            </Form.Group>
            <Form.Group controlId="editUsername">
              <Form.Label>Username</Form.Label>
              <Form.Control
                type="text"
                name="UserName"
                value={editFormData.UserName}
                onChange={handleEditChange}
              />
            </Form.Group>
            <Form.Group controlId="editAddress">
              <Form.Label>Address</Form.Label>
              <Form.Control
                type="text"
                name="UserAddress"
                value={editFormData.UserAddress}
                onChange={handleEditChange}
              />
            </Form.Group>
            <Form.Group controlId="editOldPassword">
              <Form.Label>Old Password</Form.Label>
              <Form.Control
                type="password"
                name="OldPassword"
                value={editFormData.OldPassword}
                onChange={handleEditChange}
              />
            </Form.Group>
            <Form.Group controlId="editNewPassword">
              <Form.Label>New Password</Form.Label>
              <Form.Control
                type="password"
                name="NewPassword"
                value={editFormData.NewPassword}
                onChange={handleEditChange}
              />
            </Form.Group>
          </Form>
        </Modal.Body>
        <Modal.Footer>
          <Button variant="secondary" onClick={() => setShowEditModal(false)}>
            Cancel
          </Button>
          <Button variant="primary" onClick={handleEditSubmit}>
            Save Changes
          </Button>
        </Modal.Footer>
      </Modal>
    </div>
  );
};

export default StaffSettings;
